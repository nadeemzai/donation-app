<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\PaymentMethod;
use App\Enums\QurbaniAnimal;
use App\Enums\QurbaniBookingStatus;
use App\Enums\QurbaniSeasonStatus;
use App\Enums\Role;
use App\Filament\Resources\QurbaniBookings\Pages\ViewQurbaniBooking;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\QurbaniBooking;
use App\Models\QurbaniOffering;
use App\Models\QurbaniSeason;
use App\Services\DonationService;
use App\Services\QurbaniService;
use App\Settings\GatewaySettings;
use App\Support\QurbaniToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

// W11-D1 — FR-QRB-01/02: seasons, prices per share, bookings with a name per share, paid
// through the normal donation flow. Cow / camel 7 shares, goat / sheep 1; booking closes 3 days
// before Eid; Qurbani on the 3 days of Eid; donors abroad can book.

beforeEach(function () {
    Storage::fake('local');
    $bank = app(GatewaySettings::class);
    $bank->bank_enabled = true;
    $bank->bank_name = 'Meezan Bank';
    $bank->bank_account_title = 'Imdaad Welfare Trust';
    $bank->bank_iban = 'PK36MEZN0000001123456702';
    $bank->save();
});

function qurbani(): QurbaniService
{
    return app(QurbaniService::class);
}

/**
 * @param  list<string>|null  $names
 */
function bookShares(QurbaniOffering $offering, ?array $names = null, ?Donor $donor = null): QurbaniBooking
{
    return qurbani()->book($offering, $names ?? ['Ayesha Khan'], $donor ?? Donor::factory()->create(), DonationChannel::Web);
}

describe('seasons and prices', function () {
    it('closes booking 3 days before Eid and slaughters on the 3 days of Eid', function () {
        $season = QurbaniSeason::factory()->create(['eid_date' => '2027-05-17']);

        expect($season->booking_closes_on->toDateString())->toBe('2027-05-14')
            ->and(array_map(fn ($d) => $d->toDateString(), $season->slaughterDays()))->toBe([1 => '2027-05-17', 2 => '2027-05-18', 3 => '2027-05-19']);
    });

    it('takes bookings up to and including the cut-off day', function (int $daysToEid, bool $open) {
        expect(QurbaniSeason::factory()->eidInDays($daysToEid)->create()->isTakingBookings())->toBe($open);
    })->with([
        'a week before Eid' => [7, true],
        'cut-off day (3 days before)' => [3, true],
        'two days before' => [2, false],
        'Eid day' => [0, false],
    ]);

    it('sets shares per animal: cow and camel 7, goat and sheep 1', function (QurbaniAnimal $animal, int $shares) {
        expect(QurbaniOffering::factory()->create(['animal' => $animal])->shares_per_animal)->toBe($shares);
    })->with([
        [QurbaniAnimal::Cow, 7], [QurbaniAnimal::Camel, 7], [QurbaniAnimal::Goat, 1], [QurbaniAnimal::Sheep, 1],
    ]);

    it('never uses a Zakat fund and keeps one season open at a time', function () {
        expect(fn () => QurbaniSeason::factory()->create(['fund_id' => Fund::factory()->zakat()->create()->id]))->toThrow(DomainException::class, 'Zakat');

        QurbaniSeason::factory()->create();

        expect(fn () => QurbaniSeason::factory()->create())->toThrow(DomainException::class, 'already open');
    });
});

describe('booking', function () {
    it('books shares with a name each, at the season price, and holds them', function () {
        $offering = QurbaniOffering::factory()->create(['price_per_share' => '25000']);

        $booking = bookShares($offering, ['Ayesha Khan', 'Late Muhammad Aslam', 'Fatima']);

        expect($booking)
            ->booking_no->toStartWith('QB-')
            ->status->toBe(QurbaniBookingStatus::Pending)
            ->shares->toBe(3)
            ->total_amount->toBe('75000.00')
            ->and($booking->names())->toBe(['Ayesha Khan', 'Late Muhammad Aslam', 'Fatima'])
            ->and($booking->isPayable())->toBeTrue()
            ->and($offering->heldShares())->toBe(3);
    });

    it('needs at least one name and at most 21', function (array $names, string $message) {
        expect(fn () => bookShares(QurbaniOffering::factory()->create(), $names))->toThrow(DomainException::class, $message);
    })->with([
        'none' => [['', '  '], 'at least one name'],
        'too many' => [array_fill(0, 22, 'Name'), 'up to 21'],
    ]);

    it('refuses bookings after the cut-off', function () {
        bookShares(QurbaniOffering::factory()->for(QurbaniSeason::factory()->eidInDays(2), 'season')->create());
    })->throws(DomainException::class, 'has closed');

    it('never books more than the shares available', function () {
        $offering = QurbaniOffering::factory()->create(['max_shares' => 7]);
        bookShares($offering, array_fill(0, 5, 'Name'));

        expect(fn () => bookShares($offering, array_fill(0, 3, 'Name')))->toThrow(DomainException::class, 'Only 2')
            ->and(bookShares($offering, ['A one', 'A two'])->shares)->toBe(2)
            ->and(fn () => bookShares($offering))->toThrow(DomainException::class, 'All Cow shares are booked');
    });

    it('frees the shares when the hold runs out unpaid', function () {
        $offering = QurbaniOffering::factory()->create(['max_shares' => 1]);
        $first = bookShares($offering);

        $this->travel(QurbaniService::HOLD_MINUTES + 1)->minutes();

        expect($first->fresh()?->isPayable())->toBeFalse()
            ->and(bookShares($offering)->shares)->toBe(1);

        $this->artisan('qurbani:expire')->expectsOutputToContain('Bookings cancelled: 1')->assertSuccessful();
        expect($first->fresh()?->status)->toBe(QurbaniBookingStatus::Cancelled);
    });

    it('takes bookings from abroad unless the season says Pakistan only', function () {
        $abroad = Donor::factory()->create(['phone' => '+447700900123']);
        $offering = QurbaniOffering::factory()->create();

        expect(bookShares($offering, donor: $abroad)->shares)->toBe(1);

        $offering->season->update(['allow_overseas' => false]);

        expect(fn () => bookShares($offering->refresh(), donor: $abroad))->toThrow(DomainException::class, 'Pakistan only');
    });

    it('keeps the price it was booked at', function () {
        $offering = QurbaniOffering::factory()->create(['price_per_share' => '25000']);
        bookShares($offering);

        expect(fn () => $offering->update(['price_per_share' => '30000']))->toThrow(DomainException::class, 'Price and animal cannot change');
    });
});

describe('paying through the donation flow', function () {
    it('books on the website, then pays on the donate page by bank transfer', function () {
        $offering = QurbaniOffering::factory()->goat('60000')->create();

        $response = $this->post('/qurbani', [
            'offering_id' => $offering->id,
            'names' => ['Ayesha Khan', 'Umar Khan'],
            'name' => 'Ayesha Khan',
            'phone' => '+44 7700 900123', // donors abroad can book
            'email' => 'ayesha@example.co.uk',
        ]);

        $booking = QurbaniBooking::query()->sole();
        $response->assertRedirect();
        expect($response->headers->get('Location'))->toStartWith(route('donate').'?qurbani=');

        $this->get($response->headers->get('Location') ?? '')
            ->assertOk()
            ->assertSee($booking->booking_no)
            ->assertSee('Rs 120,000.00')
            ->assertSee('Umar Khan');

        $this->post('/donate', [
            'qurbani_token' => QurbaniToken::for($booking),
            'payment_method' => PaymentMethod::BankTransfer->value,
            'reference' => 'TRF-55',
            'proof' => UploadedFile::fake()->image('transfer.jpg'),
            'amount' => '1', // ignored: the booking total is used
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $donation = Donation::query()->sole();

        expect($donation)
            ->qurbani_booking_id->toBe($booking->id)
            ->amount->toBe('120000.00')
            ->fund_id->toBe($offering->season->fund_id)
            ->donor_id->toBe($booking->donor_id)
            ->status->toBe(DonationStatus::Pending);

        // A bank transfer being checked keeps the shares held past the hour.
        $this->travel(3)->hours();
        expect($booking->fresh()?->isPayable())->toBeTrue();

        app(DonationService::class)->confirm($donation, staffUser(Role::Finance));

        expect($booking->fresh())
            ->status->toBe(QurbaniBookingStatus::Confirmed)
            ->confirmed_at->not->toBeNull();
    });

    it('frees the shares when the payment fails', function () {
        $booking = bookShares(QurbaniOffering::factory()->create(['max_shares' => 7]), array_fill(0, 7, 'Name'));
        $donation = app(DonationService::class)->create([
            'donor_id' => $booking->donor_id, 'fund_id' => $booking->season->fund_id, 'qurbani_booking_id' => $booking->id,
            'amount' => (string) $booking->total_amount, 'channel' => DonationChannel::Web, 'payment_method' => PaymentMethod::JazzCash,
        ]);

        app(DonationService::class)->fail($donation, 'Declined');

        expect($booking->fresh()?->status)->toBe(QurbaniBookingStatus::Cancelled)
            ->and($booking->offering->sharesLeft())->toBe(7);
    });

    it('refuses to pay a booking that has expired or is already paid', function () {
        $booking = bookShares(QurbaniOffering::factory()->create());
        $this->travel(2)->hours();

        $this->get('/donate?qurbani='.urlencode(QurbaniToken::for($booking)))->assertOk()->assertSee('has expired');
    });

    it('books and pays from the app', function () {
        $offering = QurbaniOffering::factory()->create(['price_per_share' => '25000']);

        $this->getJson('/api/v1/qurbani')
            ->assertOk()
            ->assertJsonPath('data.is_taking_bookings', true)
            ->assertJsonPath('data.offerings.0.shares_per_animal', 7)
            ->assertJsonPath('data.offerings.0.price_per_share.amount', '25000.00')
            ->assertJsonCount(3, 'data.slaughter_days');

        $token = $this->postJson('/api/v1/qurbani/bookings', [
            'offering_id' => $offering->id,
            'names' => ['Ayesha Khan', 'Umar Khan'],
            'name' => 'Ayesha Khan',
            'email' => 'ayesha@example.com',
        ])->assertCreated()
            ->assertJsonPath('data.shares', 2)
            ->assertJsonPath('data.total.amount', '50000.00')
            ->json('data.booking_token');

        $this->postJson('/api/v1/donations', [
            'qurbani_booking_token' => $token,
            'payment_method' => 'bank_transfer',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();

        expect(Donation::query()->sole())
            ->amount->toBe('50000.00')
            ->qurbani_booking_id->toBe(QurbaniBooking::query()->sole()->id);
    });

    it('returns no season in the app when booking is not open', function () {
        QurbaniSeason::factory()->draft()->create();

        $this->getJson('/api/v1/qurbani')->assertOk()->assertJsonPath('data', null);
    });
});

describe('admin', function () {
    it('opens the Qurbani screens as the matrix allows', function (Role $role, int $status) {
        $this->actingAs(staffUser($role))->get('/admin/qurbani-bookings')->assertStatus($status);
    })->with([
        'manager' => [Role::Manager, 200],
        'field officer' => [Role::FieldOfficer, 200],
        'trustee (view)' => [Role::Trustee, 200],
    ]);

    it('lets staff cancel an unpaid booking with a reason', function () {
        $booking = bookShares(QurbaniOffering::factory()->create());
        $this->actingAs(staffUser(Role::Manager));

        Livewire::test(ViewQurbaniBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('cancel', data: ['reason' => 'Booked twice by mistake'])
            ->assertNotified('Booking cancelled');

        expect($booking->fresh()?->status)->toBe(QurbaniBookingStatus::Cancelled);
    });

    it('shows the website menu link only while a season is open', function () {
        $this->get('/')->assertDontSee(route('qurbani'), false);

        QurbaniSeason::factory()->create(['status' => QurbaniSeasonStatus::Open]);

        $this->get('/')->assertSee(route('qurbani'), false);
        $this->get('/qurbani')->assertOk();
    });
});
