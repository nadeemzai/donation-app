<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\QurbaniBookingStatus;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\QurbaniBooking;
use App\Models\QurbaniOffering;
use App\Models\QurbaniShare;
use App\Models\User;
use App\Support\Money;
use App\Support\Phone;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Qurbani bookings (Week 11 Day 1, FR-QRB-01/02). The only place a booking changes status.
 *
 *   book()     → pending, shares held for HOLD_MINUTES while the donor pays (the hold lasts
 *                as long as a donation for it is still open, e.g. a bank transfer being checked)
 *   confirm()  → the booking's donation was paid (DonationPaid)
 *   release()  → its donation failed or was cancelled (DonationClosed): shares are freed
 *   cancel()   → staff cancel a booking that is not paid
 *
 * Booking closes 3 days before Eid; one booking holds up to MAX_SHARES shares.
 */
class QurbaniService
{
    public const HOLD_MINUTES = 60;

    public const MAX_SHARES = 21;

    /**
     * @param  list<string>  $names  one per share — on whose behalf the Qurbani is offered
     */
    public function book(QurbaniOffering $offering, array $names, Donor $donor, DonationChannel $channel, ?User $by = null): QurbaniBooking
    {
        $names = array_values(array_filter(array_map(fn (string $n): string => trim($n), $names), fn (string $n): bool => $n !== ''));
        $shares = count($names);

        if ($shares < 1) {
            throw new DomainException('Enter at least one name — one for each share.');
        }

        if ($shares > self::MAX_SHARES) {
            throw new DomainException('One booking can have up to '.self::MAX_SHARES.' shares. Please make a second booking for more.');
        }

        return DB::transaction(function () use ($offering, $names, $shares, $donor, $channel, $by): QurbaniBooking {
            $locked = QurbaniOffering::query()->lockForUpdate()->with('season')->findOrFail($offering->id);
            $season = $locked->season;

            if (! $season->isTakingBookings()) {
                throw new DomainException(__('Qurbani booking for :season has closed. Booking closes :days days before Eid.', ['season' => $season->name, 'days' => $season::CUTOFF_DAYS]));
            }

            if (! $locked->is_active) {
                throw new DomainException(__(':animal is not available this season.', ['animal' => $locked->animal->label()]));
            }

            if (! $season->allow_overseas && ! Phone::isPakistaniMobile($donor->phone)) {
                throw new DomainException(__('This season takes bookings from Pakistan only.'));
            }

            $left = $locked->sharesLeft();

            if ($left !== null && $shares > $left) {
                throw new DomainException($left === 0
                    ? __('All :animal shares are booked. Please choose another animal.', ['animal' => $locked->animal->label()])
                    : __('Only :left :animal shares are left.', ['left' => $left, 'animal' => $locked->animal->label()]));
            }

            $booking = new QurbaniBooking([
                'season_id' => $season->id,
                'offering_id' => $locked->id,
                'donor_id' => $donor->id,
                'shares' => $shares,
                'price_per_share' => (string) $locked->price_per_share,
                'channel' => $channel,
                'hold_until' => now()->addMinutes(self::HOLD_MINUTES),
                'created_by' => $by?->id,
            ]);
            $booking->save();

            foreach ($names as $i => $name) {
                QurbaniShare::query()->create(['booking_id' => $booking->id, 'position' => $i + 1, 'name' => $name]);
            }

            activity('audit')->performedOn($booking)->causedBy($by)->event('booked')
                ->withProperties(['shares' => $shares, 'animal' => $locked->animal->value, 'total' => (string) $booking->total_amount])
                ->log("Qurbani booking {$booking->booking_no}: {$shares} × {$locked->animal->label()} for ".Money::rupees((string) $booking->total_amount));

            return $booking->load('shareRows');
        });
    }

    /** The booking can be paid now (still pending and holding its shares). */
    public function assertPayable(QurbaniBooking $booking): void
    {
        if ($booking->status === QurbaniBookingStatus::Confirmed) {
            throw new DomainException(__('Booking :no is already paid — thank you.', ['no' => $booking->booking_no]));
        }

        if (! $booking->isPayable()) {
            throw new DomainException(__('Booking :no has expired. Please book again.', ['no' => $booking->booking_no]));
        }
    }

    /** DonationPaid: the booking is confirmed (even if its hold ran out — the money is in). */
    public function confirm(Donation $donation): ?QurbaniBooking
    {
        if ($donation->qurbani_booking_id === null) {
            return null;
        }

        return DB::transaction(function () use ($donation): ?QurbaniBooking {
            $booking = QurbaniBooking::query()->lockForUpdate()->find($donation->qurbani_booking_id);

            if ($booking === null || $booking->status === QurbaniBookingStatus::Confirmed) {
                return $booking;
            }

            $lateNote = $booking->status === QurbaniBookingStatus::Cancelled
                ? ' (paid after it was cancelled — check shares)'
                : '';

            // A cancelled booking that is paid anyway (a slow bank transfer) is honoured: query
            // update, because a cancelled booking otherwise keeps its status.
            QurbaniBooking::query()->whereKey($booking->id)->update([
                'status' => QurbaniBookingStatus::Confirmed->value,
                'confirmed_at' => now(),
                'hold_until' => null,
                'cancelled_at' => null,
                'cancel_reason' => null,
                'updated_at' => now(),
            ]);

            activity('audit')->performedOn($booking)->event('confirmed')
                ->withProperties(['donation_no' => $donation->donation_no])
                ->log("Qurbani booking {$booking->booking_no} confirmed by donation {$donation->donation_no}{$lateNote}");

            return $booking->refresh();
        });
    }

    /** DonationClosed: free the shares, unless another payment for the booking is still open. */
    public function release(Donation $donation, ?string $reason = null): ?QurbaniBooking
    {
        if ($donation->qurbani_booking_id === null) {
            return null;
        }

        $booking = QurbaniBooking::query()->find($donation->qurbani_booking_id);

        if ($booking === null || $booking->status !== QurbaniBookingStatus::Pending) {
            return $booking;
        }

        if ($booking->donations()->where('status', DonationStatus::Pending->value)->exists()) {
            return $booking;
        }

        return $this->cancel($booking, $reason ?? 'Payment did not go through.');
    }

    public function cancel(QurbaniBooking $booking, string $reason, ?User $by = null): QurbaniBooking
    {
        $reason = trim($reason) !== '' ? trim($reason) : throw new DomainException('Write why the booking is cancelled.');

        DB::transaction(function () use ($booking, $reason, $by): void {
            $locked = QurbaniBooking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($locked->status !== QurbaniBookingStatus::Pending) {
                throw new DomainException("Booking {$locked->booking_no} is {$locked->status->label()} — only an unpaid booking can be cancelled. A paid one is refunded through its donation.");
            }

            $locked->forceFill([
                'status' => QurbaniBookingStatus::Cancelled,
                'cancelled_at' => now(),
                'cancel_reason' => mb_substr($reason, 0, 255),
                'hold_until' => null,
            ])->save();

            activity('audit')->performedOn($locked)->causedBy($by)->event('cancelled')
                ->withProperties(['reason' => $reason])
                ->log("Qurbani booking {$locked->booking_no} cancelled");
        });

        return $booking->refresh();
    }

    /**
     * Pending bookings whose hold ran out with no open payment are cancelled, so they stop
     * showing as "waiting" (their shares were already free). Run by the scheduler.
     */
    public function expireHolds(): int
    {
        $count = 0;

        QurbaniBooking::query()
            ->where('status', QurbaniBookingStatus::Pending->value)
            ->where('hold_until', '<=', now())
            ->whereDoesntHave('donations', fn ($d) => $d->where('status', DonationStatus::Pending->value))
            ->lazyById(100)
            ->each(function (QurbaniBooking $booking) use (&$count): void {
                $this->cancel($booking, 'Not paid within '.self::HOLD_MINUTES.' minutes.');
                $count++;
            });

        return $count;
    }
}
