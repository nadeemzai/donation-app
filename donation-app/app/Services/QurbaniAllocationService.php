<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QurbaniAnimalStatus;
use App\Enums\QurbaniBookingStatus;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\QurbaniAnimal;
use App\Models\QurbaniBooking;
use App\Models\QurbaniOffering;
use App\Models\QurbaniSeason;
use App\Models\QurbaniShare;
use App\Models\User;
use App\Services\Messaging\NotificationService;
use App\Services\Messaging\TemplateRenderer;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Qurbani operations (Week 11 Day 2, FR-QRB-03): shares onto tagged animals, and each animal
 * through allocated → slaughtered → distributed, with one message to the donor at each stage.
 *
 *   allocate()        → every confirmed booking's shares onto animals of its type; a booking's
 *                       shares stay on one animal when there is room (best fit), otherwise fill
 *                       the next animals. Bookings with all shares placed get "allocated".
 *   moveShare()       → staff move one share to another animal with room
 *   markSlaughtered() → only on the 3 days of Eid; donors are told once all their animals are done
 *   markDistributed() → after slaughter; donors are told once all their meat is distributed
 *
 * A donor gets each message once per booking (checked against the Message log).
 */
class QurbaniAllocationService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly TemplateRenderer $renderer,
    ) {}

    /**
     * @return array{allocated: int, waiting: int, notified: int}
     */
    public function allocate(QurbaniSeason $season, ?User $by = null): array
    {
        $stats = ['allocated' => 0, 'waiting' => 0, 'notified' => 0];

        foreach ($season->offerings()->get() as $offering) {
            DB::transaction(function () use ($offering, &$stats): void {
                $animals = QurbaniAnimal::query()
                    ->where('offering_id', $offering->id)
                    ->whereIn('status', [QurbaniAnimalStatus::Available->value, QurbaniAnimalStatus::Allocated->value])
                    ->lockForUpdate()
                    ->orderBy('slaughter_day')
                    ->orderBy('tag_no')
                    ->get();

                /** @var array<int, int> $free animal id => places left */
                $free = $animals->mapWithKeys(fn (QurbaniAnimal $a): array => [$a->id => max(0, $offering->shares_per_animal - $a->sharesUsed())])->all();

                foreach ($this->bookingsToPlace($offering) as $booking) {
                    $shares = $booking->shareRows->whereNull('animal_id')->values();
                    $placed = $this->place($shares, $free);
                    $stats['allocated'] += $placed;
                    $stats['waiting'] += $shares->count() - $placed;
                }

                QurbaniAnimal::query()->whereIn('id', array_keys($free))->get()->each(fn (QurbaniAnimal $a) => $this->syncStatus($a));
            });
        }

        // Tell donors whose shares are all placed (once).
        QurbaniBooking::query()
            ->where('season_id', $season->id)
            ->where('status', QurbaniBookingStatus::Confirmed->value)
            ->whereDoesntHave('shareRows', fn ($q) => $q->whereNull('animal_id'))
            ->with(['donor', 'offering', 'season', 'shareRows.animal'])
            ->lazyById(100)
            ->each(function (QurbaniBooking $booking) use (&$stats): void {
                $stats['notified'] += $this->notify($booking, MessageTemplate::QURBANI_ALLOCATED) ? 1 : 0;
            });

        activity('audit')->performedOn($season)->causedBy($by)->event('qurbani_allocated')
            ->withProperties($stats)
            ->log("Qurbani allocation for {$season->name}: {$stats['allocated']} shares placed, {$stats['waiting']} waiting for an animal");

        return $stats;
    }

    public function moveShare(QurbaniShare $share, QurbaniAnimal $to, ?User $by = null): QurbaniShare
    {
        DB::transaction(function () use ($share, $to, $by): void {
            $share = QurbaniShare::query()->lockForUpdate()->with('booking')->findOrFail($share->id);
            $to = QurbaniAnimal::query()->lockForUpdate()->with('offering')->findOrFail($to->id);
            $from = $share->animal_id !== null ? QurbaniAnimal::query()->lockForUpdate()->find($share->animal_id) : null;

            if ($share->booking->status !== QurbaniBookingStatus::Confirmed) {
                throw new DomainException('Only shares of a paid booking go on an animal.');
            }

            if ($to->offering_id !== $share->booking->offering_id) {
                throw new DomainException("This share is for a {$share->booking->offering->animal->label()} — choose an animal of that type.");
            }

            if (! $to->isOpen() || ($from !== null && ! $from->isOpen())) {
                throw new DomainException('Shares cannot be moved once the Qurbani is done.');
            }

            if ($to->id !== $from?->id && $to->freeShares() < 1) {
                throw new DomainException("Animal {$to->tag_no} is full.");
            }

            $share->forceFill(['animal_id' => $to->id])->save();

            $this->syncStatus($to);

            if ($from !== null) {
                $this->syncStatus($from);
            }

            activity('audit')->performedOn($share->booking)->causedBy($by)->event('share_moved')
                ->withProperties(['share' => $share->position, 'from' => $from?->tag_no, 'to' => $to->tag_no])
                ->log("Share #{$share->position} of {$share->booking->booking_no} moved to {$to->tag_no}");
        });

        return $share->refresh();
    }

    /** Qurbani done for this animal — only on the 3 days of Eid. */
    public function markSlaughtered(QurbaniAnimal $animal, ?User $by = null, ?Carbon $at = null): QurbaniAnimal
    {
        $at ??= now();

        DB::transaction(function () use ($animal, $by, $at): void {
            $locked = QurbaniAnimal::query()->lockForUpdate()->with('season')->findOrFail($animal->id);

            if ($locked->status !== QurbaniAnimalStatus::Allocated) {
                throw new DomainException("Animal {$locked->tag_no} is {$locked->status->label()} — only an animal with shares on it can be marked slaughtered.");
            }

            $day = (int) $locked->season->eid_date->copy()->startOfDay()->diffInDays($at->copy()->startOfDay(), false) + 1;

            if ($day < 1 || $day > QurbaniSeason::SLAUGHTER_DAYS) {
                throw new DomainException('Qurbani is offered only on the 3 days of Eid ('.$locked->season->eid_date->format('d M').' – '.$locked->season->eid_date->copy()->addDays(QurbaniSeason::SLAUGHTER_DAYS - 1)->format('d M Y').').');
            }

            $locked->forceFill(['status' => QurbaniAnimalStatus::Slaughtered, 'slaughtered_at' => $at, 'slaughter_day' => $day])->save();

            activity('audit')->performedOn($locked)->causedBy($by)->event('slaughtered')
                ->withProperties(['day' => $day])
                ->log("Qurbani animal {$locked->tag_no} slaughtered on Eid day {$day}");
        });

        $this->notifyBookingsOf($animal, QurbaniAnimalStatus::Slaughtered, MessageTemplate::QURBANI_DONE);

        return $animal->refresh();
    }

    public function markDistributed(QurbaniAnimal $animal, ?User $by = null): QurbaniAnimal
    {
        DB::transaction(function () use ($animal, $by): void {
            $locked = QurbaniAnimal::query()->lockForUpdate()->findOrFail($animal->id);

            if ($locked->status !== QurbaniAnimalStatus::Slaughtered) {
                throw new DomainException("Animal {$locked->tag_no} is {$locked->status->label()} — the meat is distributed after the Qurbani.");
            }

            $locked->forceFill(['status' => QurbaniAnimalStatus::Distributed, 'distributed_at' => now()])->save();

            activity('audit')->performedOn($locked)->causedBy($by)->event('distributed')
                ->log("Meat of Qurbani animal {$locked->tag_no} distributed");
        });

        $this->notifyBookingsOf($animal, QurbaniAnimalStatus::Distributed, MessageTemplate::QURBANI_DISTRIBUTED);

        return $animal->refresh();
    }

    /**
     * Confirmed bookings of this type with shares not yet on an animal, oldest payment first.
     *
     * @return Collection<int, QurbaniBooking>
     */
    private function bookingsToPlace(QurbaniOffering $offering): Collection
    {
        return QurbaniBooking::query()
            ->where('offering_id', $offering->id)
            ->where('status', QurbaniBookingStatus::Confirmed->value)
            ->whereHas('shareRows', fn ($q) => $q->whereNull('animal_id'))
            ->with('shareRows')
            ->orderBy('confirmed_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Put $shares on animals: one animal with room for all of them (the tightest fit), or else
     * across the animals with room, in order. Updates $free. Returns how many were placed.
     *
     * @param  Collection<int, QurbaniShare>  $shares
     * @param  array<int, int>  $free
     */
    private function place(Collection $shares, array &$free): int
    {
        $need = $shares->count();
        $fits = array_filter($free, fn (int $left): bool => $left >= $need);

        if ($fits !== []) {
            asort($fits);
            $targets = array_fill(0, $need, (int) array_key_first($fits));
        } else {
            $targets = [];

            foreach ($free as $id => $left) {
                for ($i = 0; $i < $left && count($targets) < $need; $i++) {
                    $targets[] = $id;
                }
            }
        }

        foreach ($targets as $i => $animalId) {
            $shares[$i]->forceFill(['animal_id' => $animalId])->save();
            $free[$animalId]--;
        }

        return count($targets);
    }

    private function syncStatus(QurbaniAnimal $animal): void
    {
        if (! $animal->isOpen()) {
            return;
        }

        $status = $animal->shares()->exists() ? QurbaniAnimalStatus::Allocated : QurbaniAnimalStatus::Available;

        if ($animal->status !== $status) {
            $animal->forceFill(['status' => $status])->save();
        }
    }

    /** Tell each booking on $animal once all of that booking's animals have reached $stage. */
    private function notifyBookingsOf(QurbaniAnimal $animal, QurbaniAnimalStatus $stage, string $template): void
    {
        $reached = $stage === QurbaniAnimalStatus::Slaughtered
            ? [QurbaniAnimalStatus::Slaughtered->value, QurbaniAnimalStatus::Distributed->value]
            : [QurbaniAnimalStatus::Distributed->value];

        QurbaniBooking::query()
            ->whereHas('shareRows', fn ($q) => $q->where('animal_id', $animal->id))
            ->with(['donor', 'offering', 'season', 'shareRows.animal'])
            ->get()
            ->filter(fn (QurbaniBooking $b): bool => $b->shareRows->every(fn (QurbaniShare $s): bool => $s->animal !== null && in_array($s->animal->status->value, $reached, true)))
            ->each(fn (QurbaniBooking $b) => $this->notify($b, $template));
    }

    /** One message per booking per template. */
    private function notify(QurbaniBooking $booking, string $template): bool
    {
        $sent = MessageLog::query()
            ->where('template', $template)
            ->where('subject_type', $booking->getMorphClass())
            ->where('subject_id', $booking->id)
            ->exists();

        if ($sent) {
            return false;
        }

        $logs = $this->notifications->send($booking->donor, $template, $this->renderer->varsForQurbani($booking, $booking->donor->prefersUrdu()), $booking);

        return $logs !== [];
    }
}
