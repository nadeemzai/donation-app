<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Filament\Resources\Donations\Pages\CreateDonation;
use App\Filament\Resources\Donations\Pages\ListDonations;
use App\Filament\Resources\Donations\Pages\ViewDonation;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\User;
use App\Services\DonationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

// FR-DON-01/02, BR-03, §3.1 (areas: donations, proof_verification) — donations screen

beforeEach(fn () => Storage::fake('local'));

/**
 * A donation entered through the service (pending unless $paid).
 *
 * @param  array<string, mixed>  $overrides
 */
function screenDonation(array $overrides = [], bool $paid = false): Donation
{
    $service = app(DonationService::class);
    $by = User::factory()->create();

    $donation = $service->create(array_merge([
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => '5000.00',
        'channel' => DonationChannel::Admin,
        'payment_method' => PaymentMethod::Cheque,
        'reference' => 'CHQ-100200',
    ], $overrides), $by);

    return $paid ? $service->confirm($donation, $by) : $donation;
}

// ── access ──────────────────────────────────────────────────────────────

it('lets each role open the donations list as the matrix allows', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/donations')->assertStatus($status);
})->with([
    'super admin' => [Role::SuperAdmin, 200],
    'finance' => [Role::Finance, 200],
    'manager (view)' => [Role::Manager, 200],
    'campaign manager (view)' => [Role::CampaignManager, 200],
    'trustee (view)' => [Role::Trustee, 200],
    'auditor (view)' => [Role::Auditor, 200],
    'field officer' => [Role::FieldOfficer, 403],
]);

it('lets only Finance and Super Admin record offline donations', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/donations/create')->assertStatus($status);
})->with([
    'finance' => [Role::Finance, 200],
    'super admin' => [Role::SuperAdmin, 200],
    'manager' => [Role::Manager, 403],
    'auditor' => [Role::Auditor, 403],
]);

it('shows a donation to view-only roles', function () {
    $donation = screenDonation();

    $this->actingAs(staffUser(Role::Auditor))
        ->get("/admin/donations/{$donation->id}")
        ->assertOk()
        ->assertSee($donation->donation_no);
});

it('has no edit screen and never deletes', function () {
    $donation = screenDonation();

    $this->actingAs(staffUser(Role::SuperAdmin))->get("/admin/donations/{$donation->id}/edit")->assertNotFound();
});

// ── recording offline donations ─────────────────────────────────────────

it('records a cash donation and confirms it straight away (BR-03)', function () {
    $finance = staffUser(Role::Finance);
    $fund = Fund::factory()->create();
    $donor = Donor::factory()->create();
    $this->actingAs($finance);

    Livewire::test(CreateDonation::class)
        ->fillForm([
            'donor_id' => $donor->id,
            'fund_id' => $fund->id,
            'amount' => '2500',
            'payment_method' => PaymentMethod::Cash->value,
            'received_at' => now()->subHour()->toDateTimeString(),
            'confirm_now' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Donation::sole())
        ->status->toBe(DonationStatus::Paid)
        ->amount->toBe('2500.00')
        ->channel->toBe(DonationChannel::Admin)
        ->recorded_by->toBe($finance->id)
        ->confirmed_by->toBe($finance->id)
        ->and($fund->fresh()->balance)->toBe('2500.00');
});

it('needs a cheque number and a proof file for a cheque', function () {
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(CreateDonation::class)
        ->fillForm([
            'donor_id' => Donor::factory()->create()->id,
            'fund_id' => Fund::factory()->create()->id,
            'amount' => '10000',
            'payment_method' => PaymentMethod::Cheque->value,
            'reference' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['reference' => 'required', 'proof' => 'required']);

    expect(Donation::query()->count())->toBe(0);
});

it('records a cheque with its proof, waiting to clear', function () {
    $this->actingAs(staffUser(Role::Finance));
    $fund = Fund::factory()->create();

    Livewire::test(CreateDonation::class)
        ->fillForm([
            'donor_id' => Donor::factory()->create()->id,
            'fund_id' => $fund->id,
            'amount' => '10000',
            'payment_method' => PaymentMethod::Cheque->value,
            'reference' => 'CHQ-004512',
            'proof' => UploadedFile::fake()->image('cheque.jpg'),
            'confirm_now' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $donation = Donation::sole();
    expect($donation->status)->toBe(DonationStatus::Pending)
        ->and($donation->reference)->toBe('CHQ-004512')
        ->and($donation->getFirstMedia(Donation::PROOF)?->disk)->toBe('local')
        ->and($fund->fresh()->balance)->toBe('0.00');
});

it('takes the fund from the chosen campaign', function () {
    $this->actingAs(staffUser(Role::Finance));
    $campaign = Campaign::factory()->live()->create();

    Livewire::test(CreateDonation::class)
        ->fillForm([
            'donor_id' => Donor::factory()->create()->id,
            'campaign_id' => $campaign->id,
            'amount' => '4000',
            'payment_method' => PaymentMethod::Cash->value,
            'confirm_now' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Donation::sole()->fund_id)->toBe($campaign->fund_id)
        ->and($campaign->fresh()->raised_amount)->toBe('4000.00');
});

it('does not offer inactive funds or archived donors', function () {
    $this->actingAs(staffUser(Role::Finance));
    $donor = Donor::factory()->create();
    $donor->delete();

    Livewire::test(CreateDonation::class)
        ->fillForm([
            'donor_id' => $donor->id,
            'fund_id' => Fund::factory()->inactive()->create()->id,
            'amount' => '1000',
            'payment_method' => PaymentMethod::Cash->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['donor_id', 'fund_id']);
});

it('rejects badly written amounts', function (string $amount) {
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(CreateDonation::class)
        ->fillForm([
            'donor_id' => Donor::factory()->create()->id,
            'fund_id' => Fund::factory()->create()->id,
            'amount' => $amount,
            'payment_method' => PaymentMethod::Cash->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['amount']);
})->with(['1,000', '10.555', 'abc', '-50']);

// ── confirm / cancel / refund ───────────────────────────────────────────

it('lets Finance confirm a cleared cheque', function () {
    $donation = screenDonation();
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(ViewDonation::class, ['record' => $donation->getRouteKey()])
        ->callAction('confirm', data: ['reference' => 'CHQ-100200', 'paid_at' => now()->toDateTimeString()])
        ->assertNotified('Donation confirmed');

    expect($donation->fresh()->status)->toBe(DonationStatus::Paid)
        ->and($donation->fund->fresh()->balance)->toBe('5000.00');
});

it('hides money buttons from view-only roles', function () {
    $pending = screenDonation();
    $paid = screenDonation(paid: true);
    $this->actingAs(staffUser(Role::Manager));

    Livewire::test(ViewDonation::class, ['record' => $pending->getRouteKey()])
        ->assertActionHidden('confirm')
        ->assertActionHidden('cancel');

    Livewire::test(ViewDonation::class, ['record' => $paid->getRouteKey()])
        ->assertActionHidden('refund');
});

it('cancels a pending donation with a reason', function () {
    $donation = screenDonation();
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(ViewDonation::class, ['record' => $donation->getRouteKey()])
        ->callAction('cancel', data: ['reason' => 'Cheque bounced'])
        ->assertNotified('Donation cancelled');

    expect($donation->fresh()->status)->toBe(DonationStatus::Cancelled);
});

it('only offers Refund on paid donations', function () {
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(ViewDonation::class, ['record' => screenDonation()->getRouteKey()])
        ->assertActionHidden('refund')
        ->assertActionVisible('confirm');

    Livewire::test(ViewDonation::class, ['record' => screenDonation(paid: true)->getRouteKey()])
        ->assertActionVisible('refund')
        ->assertActionHidden('confirm');
});

it('refunds part of a paid donation', function () {
    $donation = screenDonation(paid: true);
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(ViewDonation::class, ['record' => $donation->getRouteKey()])
        ->callAction('refund', data: [
            'amount' => '1500',
            'method' => PaymentMethod::BankTransfer->value,
            'reference' => 'IBFT-77120',
            'reason' => 'Donor gave twice by mistake',
        ])
        ->assertNotified('Refund recorded');

    expect($donation->fresh())
        ->status->toBe(DonationStatus::PartiallyRefunded)
        ->refunded_amount->toBe('1500.00')
        ->and($donation->fund->fresh()->balance)->toBe('3500.00');
});

it('explains why a refund is refused and changes nothing', function () {
    $donation = screenDonation(paid: true);
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(ViewDonation::class, ['record' => $donation->getRouteKey()])
        ->callAction('refund', data: [
            'amount' => '5000.01',
            'method' => PaymentMethod::Cash->value,
            'reason' => 'Too much',
        ])
        ->assertNotified('Not refunded');

    expect($donation->fresh()->status)->toBe(DonationStatus::Paid)
        ->and($donation->fund->fresh()->balance)->toBe('5000.00');
});

it('lets staff download the proof file', function () {
    $donation = screenDonation();
    $donation->addMedia(UploadedFile::fake()->image('cheque.jpg'))->toMediaCollection(Donation::PROOF);
    $this->actingAs(staffUser(Role::Auditor));

    Livewire::test(ViewDonation::class, ['record' => $donation->getRouteKey()])
        ->callAction('proof')
        ->assertFileDownloaded('cheque.jpg');
});

// ── export ──────────────────────────────────────────────────────────────

it('offers Excel export only to roles with export access', function () {
    $this->actingAs(staffUser(Role::Manager));
    Livewire::test(ListDonations::class)->assertActionHidden('export');

    $this->actingAs(staffUser(Role::Auditor));
    Livewire::test(ListDonations::class)->assertActionHidden('export');

    $this->actingAs(staffUser(Role::Finance));
    Livewire::test(ListDonations::class)->assertActionVisible('export');
});

it('exports the filtered donations to Excel and logs it', function () {
    $fund = Fund::factory()->create();
    screenDonation(['fund_id' => $fund->id], paid: true);
    screenDonation(['fund_id' => $fund->id]);
    screenDonation(); // another fund — filtered out
    $this->actingAs(staffUser(Role::SuperAdmin));

    Livewire::test(ListDonations::class)
        ->filterTable('fund', $fund->id)
        ->callAction('export')
        ->assertFileDownloaded();

    $log = Activity::query()->where('event', 'exported')->sole();
    expect(data_get($log->properties, 'rows'))->toBe(2);
});
