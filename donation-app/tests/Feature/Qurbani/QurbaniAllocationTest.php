<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\MessageChannel;
use App\Enums\QurbaniAnimal as AnimalType;
use App\Enums\QurbaniAnimalStatus;
use App\Enums\QurbaniBookingStatus;
use App\Enums\Role;
use App\Filament\Resources\QurbaniAnimals\Pages\ListQurbaniAnimals;
use App\Filament\Resources\QurbaniBookings\Pages\ViewQurbaniBooking;
use App\Models\Donor;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\QurbaniAnimal;
use App\Models\QurbaniBooking;
use App\Models\QurbaniOffering;
use App\Services\QurbaniAllocationService;
use App\Services\QurbaniService;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

// W11-D2 — FR-QRB-03: shares onto tagged animals, allocated → slaughtered → distributed, and a
// message to the donor at each stage (once per booking).

function allocation(): QurbaniAllocationService
{
    return app(QurbaniAllocationService::class);
}

/** A paid booking with $shares names. */
function paidBooking(QurbaniOffering $offering, int $shares = 1, ?Donor $donor = null): QurbaniBooking
{
    $names = array_map(fn (int $i): string => "Name {$i}", range(1, $shares));
    $booking = app(QurbaniService::class)->book($offering, $names, $donor ?? Donor::factory()->create(), DonationChannel::Web);
    $booking->forceFill(['status' => QurbaniBookingStatus::Confirmed, 'confirmed_at' => now(), 'hold_until' => null])->save();

    return $booking->refresh();
}

function cow(QurbaniOffering $offering, string $tag, int $day = 1): QurbaniAnimal
{
    return QurbaniAnimal::factory()->create(['offering_id' => $offering->id, 'tag_no' => $tag, 'slaughter_day' => $day]);
}

/**
 * @return list<string|null>
 */
function tagsOf(QurbaniBooking $booking): array
{
    return $booking->shareRows()->with('animal')->get()->map(fn ($s) => $s->animal?->tag_no)->all();
}

function qurbaniEmails(string $template): int
{
    return MessageLog::query()->where('template', $template)->where('channel', MessageChannel::Email->value)->count();
}

describe('allocation', function () {
    it('keeps each booking on one animal, choosing the tightest fit', function () {
        $offering = QurbaniOffering::factory()->create();
        cow($offering, 'C-1');
        cow($offering, 'C-2');

        $three = paidBooking($offering, 3);
        $five = paidBooking($offering, 5);
        $four = paidBooking($offering, 4);

        $stats = allocation()->allocate($offering->season);

        expect($stats)->toMatchArray(['allocated' => 12, 'waiting' => 0])
            ->and(array_unique(tagsOf($three)))->toBe(['C-1'])
            ->and(array_unique(tagsOf($five)))->toBe(['C-2'])
            ->and(array_unique(tagsOf($four)))->toBe(['C-1']); // C-1 had exactly 4 left
    });

    it('splits a booking across animals only when no single animal has room', function () {
        $offering = QurbaniOffering::factory()->create();
        cow($offering, 'C-1');
        cow($offering, 'C-2');
        paidBooking($offering, 6);
        paidBooking($offering, 6);
        $two = paidBooking($offering, 2);

        allocation()->allocate($offering->season);

        expect(tagsOf($two))->toBe(['C-1', 'C-2']);
    });

    it('leaves shares waiting when there are not enough animals, and places only paid bookings', function () {
        $offering = QurbaniOffering::factory()->create(['animal' => AnimalType::Goat, 'price_per_share' => '60000']);
        cow($offering, 'G-1');
        $paid = paidBooking($offering, 2);
        $unpaid = app(QurbaniService::class)->book($offering, ['Unpaid'], Donor::factory()->create(), DonationChannel::Web);

        $stats = allocation()->allocate($offering->season);

        expect($stats)->toMatchArray(['allocated' => 1, 'waiting' => 1, 'notified' => 0])
            ->and(tagsOf($paid))->toBe(['G-1', null])
            ->and(tagsOf($unpaid))->toBe([null]);

        cow($offering, 'G-2');

        expect(allocation()->allocate($offering->season))->toMatchArray(['allocated' => 1, 'waiting' => 0, 'notified' => 1]);
    });

    it('marks animals allocated and tells each donor once, with the tag and day', function () {
        $offering = QurbaniOffering::factory()->create();
        $animal = cow($offering, 'C-7', day: 2);
        $booking = paidBooking($offering, 2);

        allocation()->allocate($offering->season);
        allocation()->allocate($offering->season); // again: nothing new

        $email = MessageLog::query()->where('template', MessageTemplate::QURBANI_ALLOCATED)->where('channel', MessageChannel::Email->value)->sole();

        expect($animal->fresh()?->status)->toBe(QurbaniAnimalStatus::Allocated)
            ->and($email->email_subject)->toContain($booking->booking_no)->toContain('C-7')->toContain('Eid day 2')
            ->and($email->body)->toContain('Name 1, Name 2');
    });
});

describe('moving a share', function () {
    it('moves a share to another animal with room and frees the old one', function () {
        $offering = QurbaniOffering::factory()->create();
        $first = cow($offering, 'C-1');
        $second = cow($offering, 'C-2');
        $booking = paidBooking($offering, 1);
        allocation()->allocate($offering->season);

        allocation()->moveShare($booking->shareRows()->sole(), $second);

        expect(tagsOf($booking))->toBe(['C-2'])
            ->and($first->fresh()?->status)->toBe(QurbaniAnimalStatus::Available)
            ->and($second->fresh()?->status)->toBe(QurbaniAnimalStatus::Allocated);
    });

    it('refuses a full animal or one of another type', function () {
        $cows = QurbaniOffering::factory()->create();
        $goats = QurbaniOffering::factory()->create(['season_id' => $cows->season_id, 'animal' => AnimalType::Goat, 'price_per_share' => '60000']);
        $full = cow($goats, 'G-1');
        paidBooking($goats);
        $booking = paidBooking($goats);
        cow($goats, 'G-2');
        allocation()->allocate($cows->season);

        expect(fn () => allocation()->moveShare($booking->shareRows()->sole(), $full))->toThrow(DomainException::class, 'full')
            ->and(fn () => allocation()->moveShare($booking->shareRows()->sole(), cow($cows, 'C-1')))->toThrow(DomainException::class, 'Goat');
    });

    it('moves a share from the booking page', function () {
        $offering = QurbaniOffering::factory()->create();
        cow($offering, 'C-1');
        $target = cow($offering, 'C-2');
        $booking = paidBooking($offering);
        allocation()->allocate($offering->season);
        $this->actingAs(staffUser(Role::Manager));

        Livewire::test(ViewQurbaniBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('moveShare', data: ['share_id' => $booking->shareRows()->sole()->id, 'animal_id' => $target->id])
            ->assertNotified('Share moved to C-2');

        expect(tagsOf($booking))->toBe(['C-2']);
    });
});

describe('Eid days', function () {
    it('marks slaughtered only on the 3 days of Eid, recording the day', function () {
        $offering = QurbaniOffering::factory()->create();
        $animal = cow($offering, 'C-1');
        paidBooking($offering);
        allocation()->allocate($offering->season);
        $eid = $offering->season->eid_date;

        $this->travelTo($eid->copy()->subDay()->setTime(10, 0));
        expect(fn () => allocation()->markSlaughtered($animal))->toThrow(DomainException::class, '3 days of Eid');

        $this->travelTo($eid->copy()->addDay()->setTime(8, 30));
        allocation()->markSlaughtered($animal);

        expect($animal->fresh())
            ->status->toBe(QurbaniAnimalStatus::Slaughtered)
            ->slaughter_day->toBe(2)
            ->slaughtered_at->not->toBeNull();
    });

    it('refuses after the third day of Eid', function () {
        $offering = QurbaniOffering::factory()->create();
        $animal = cow($offering, 'C-1');
        paidBooking($offering);
        allocation()->allocate($offering->season);

        $this->travelTo($offering->season->eid_date->copy()->addDays(3)->setTime(9, 0));

        allocation()->markSlaughtered($animal);
    })->throws(DomainException::class, '3 days of Eid');

    it('tells the donor once all their animals are done, then when the meat is distributed', function () {
        $offering = QurbaniOffering::factory()->create();
        $a = cow($offering, 'C-1');
        $b = cow($offering, 'C-2');
        paidBooking($offering, 6);
        paidBooking($offering, 6);
        $split = paidBooking($offering, 2); // one share on each cow
        allocation()->allocate($offering->season);
        expect(tagsOf($split))->toBe(['C-1', 'C-2']);

        $this->travelTo($offering->season->eid_date->copy()->setTime(9, 0));

        allocation()->markSlaughtered($a);
        $doneAfterFirst = MessageLog::query()->where('template', MessageTemplate::QURBANI_DONE)->where('subject_id', $split->id)->count();

        allocation()->markSlaughtered($b);

        expect($doneAfterFirst)->toBe(0)
            ->and(MessageLog::query()->where('template', MessageTemplate::QURBANI_DONE)->where('subject_id', $split->id)->where('channel', MessageChannel::Email->value)->count())->toBe(1)
            ->and(qurbaniEmails(MessageTemplate::QURBANI_DONE))->toBe(3);

        expect(fn () => allocation()->markDistributed(cow($offering, 'C-3')))->toThrow(DomainException::class, 'after the Qurbani');

        allocation()->markDistributed($a);
        allocation()->markDistributed($b);

        expect(qurbaniEmails(MessageTemplate::QURBANI_DISTRIBUTED))->toBe(3)
            ->and($a->fresh()?->status)->toBe(QurbaniAnimalStatus::Distributed);
    });

    it('writes to Urdu-speaking donors in Urdu', function () {
        $offering = QurbaniOffering::factory()->create();
        cow($offering, 'C-1');
        $donor = Donor::factory()->create(['preferences' => ['whatsapp_opt_in' => true, 'email_opt_in' => true, 'language' => 'ur']]);
        paidBooking($offering, 1, $donor);

        allocation()->allocate($offering->season);

        $whatsapp = MessageLog::query()->where('template', MessageTemplate::QURBANI_ALLOCATED)->where('channel', MessageChannel::WhatsApp->value)->sole();

        expect($whatsapp->body)->toContain('السلام علیکم')->toContain('گائے')->toContain('عید کا پہلا دن');
    });
});

describe('admin', function () {
    it('adds animals in bulk and allocates from the animals screen', function () {
        $offering = QurbaniOffering::factory()->create();
        paidBooking($offering, 3);
        $this->actingAs(staffUser(Role::Manager));

        Livewire::test(ListQurbaniAnimals::class)
            ->callAction('addAnimals', data: ['offering_id' => $offering->id, 'count' => 3, 'prefix' => 'C-', 'start' => 10, 'slaughter_day' => 1, 'location' => 'Farm'])
            ->assertNotified('3 animals added')
            ->callAction('allocate', data: ['season_id' => $offering->season_id])
            ->assertNotified('Allocation done');

        expect(QurbaniAnimal::query()->orderBy('tag_no')->pluck('tag_no')->all())->toBe(['C-10', 'C-11', 'C-12'])
            ->and(QurbaniAnimal::query()->where('tag_no', 'C-10')->sole()->status)->toBe(QurbaniAnimalStatus::Allocated);
    });

    it('marks animals done in bulk on Eid', function () {
        $offering = QurbaniOffering::factory()->create();
        $a = cow($offering, 'C-1');
        $b = cow($offering, 'C-2');
        paidBooking($offering, 7);
        paidBooking($offering, 7);
        allocation()->allocate($offering->season);
        $this->travelTo($offering->season->eid_date->copy()->setTime(9, 0));
        $this->actingAs(staffUser(Role::FieldOfficer));

        Livewire::test(ListQurbaniAnimals::class)
            ->selectTableRecords([$a->id, $b->id])
            ->callAction(TestAction::make('bulkSlaughtered')->table()->bulk())
            ->assertNotified('2 animals updated');

        expect($a->fresh()?->status)->toBe(QurbaniAnimalStatus::Slaughtered)
            ->and($b->fresh()?->status)->toBe(QurbaniAnimalStatus::Slaughtered);
    });

    it('never removes an animal with shares, and keeps tags unique per season', function () {
        $offering = QurbaniOffering::factory()->create();
        $animal = cow($offering, 'C-1');
        paidBooking($offering);
        allocation()->allocate($offering->season);

        expect(fn () => $animal->fresh()?->delete())->toThrow(DomainException::class, 'no shares')
            ->and(fn () => cow($offering, 'c-1'))->toThrow(DomainException::class, 'already used');
    });
});
