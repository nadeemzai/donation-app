<?php

declare(strict_types=1);

use App\Enums\DonationStatus;
use App\Enums\FundType;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\FundLedgerEntry;
use App\Services\DonorMergeService;
use Illuminate\Database\QueryException;

// FR-DON-01/02, BR-01, BR-03, BR-04, BR-05, BR-08 — the donation record

it('gets a sequential donation number and starts pending', function () {
    $year = now()->year;

    $first = Donation::factory()->create();
    $second = Donation::factory()->create();

    expect($first->donation_no)->toBe("DN-{$year}-000001")
        ->and($second->donation_no)->toBe("DN-{$year}-000002")
        ->and($first->fresh()->status)->toBe(DonationStatus::Pending)
        ->and($first->fresh()->refunded_amount)->toBe('0.00')
        ->and($first->currency)->toBe('PKR');
});

it('sends a campaign donation to the campaign\'s fund automatically', function () {
    $campaign = Campaign::factory()->create();

    $donation = Donation::factory()->create(['fund_id' => null, 'campaign_id' => $campaign->id]);

    expect($donation->fund_id)->toBe($campaign->fund_id);
});

it('refuses a campaign donation aimed at a different fund (BR-01)', function () {
    $campaign = Campaign::factory()->create();

    Donation::factory()->create(['fund_id' => Fund::factory()->create()->id, 'campaign_id' => $campaign->id]);
})->throws(DomainException::class, 'go to its own fund only');

it('needs an amount above zero', function () {
    Donation::factory()->create(['amount' => '0']);
})->throws(DomainException::class, 'more than zero');

it('freezes a paid donation\'s core facts (BR-05)', function (string $field, Closure $value) {
    $donation = Donation::factory()->paid()->create();

    $donation->update([$field => $value()]);
})->with([
    'amount' => ['amount', fn () => '99999.00'],
    'fund' => ['fund_id', fn () => Fund::factory()->create()->id],
    'donor' => ['donor_id', fn () => Donor::factory()->create()->id],
    'method' => ['payment_method', fn () => PaymentMethod::Cheque],
])->throws(DomainException::class, 'cannot be changed');

it('still allows a staff note on a paid donation', function () {
    $donation = Donation::factory()->paid()->create();
    $donation->update(['staff_note' => 'Receipt collected in person']);

    expect($donation->fresh()->staff_note)->toBe('Receipt collected in person');
});

it('lets a pending donation be corrected', function () {
    $donation = Donation::factory()->create(['amount' => '1000.00']);
    $donation->update(['amount' => '1500.00']);

    expect($donation->fresh()->amount)->toBe('1500.00');
});

it('never deletes a donation', function () {
    Donation::factory()->create()->delete();
})->throws(DomainException::class, 'never deleted');

it('does not let status or refunds be mass-assigned', function () {
    $donation = Donation::factory()->create();
    $donation->update(['status' => 'paid', 'refunded_amount' => '500.00', 'paid_at' => now()]);

    expect($donation->fresh())
        ->status->toBe(DonationStatus::Pending)
        ->refunded_amount->toBe('0.00')
        ->paid_at->toBeNull();
});

it('keeps donation numbers unique and unchangeable', function () {
    $donation = Donation::factory()->create();
    $donation->donation_no = 'DN-1999-000001';
    $donation->save();
})->throws(DomainException::class, 'can never change');

it('hides anonymous donors on public surfaces (BR-08)', function () {
    $donor = Donor::factory()->create(['name' => 'Real Name']);

    expect(Donation::factory()->for($donor)->anonymous()->create()->publicDonorName())->toBe('Anonymous')
        ->and(Donation::factory()->for($donor)->create()->publicDonorName())->toBe('Real Name');
});

it('counts only paid money (BR-03)', function () {
    Donation::factory()->create(['amount' => '1000.00']); // pending
    Donation::factory()->paid()->create(['amount' => '2500.00']);

    expect(Donation::query()->counted()->sum('amount'))->toEqual(2500);
});

it('allows only valid status changes', function () {
    expect(DonationStatus::Pending->canTransitionTo(DonationStatus::Paid))->toBeTrue()
        ->and(DonationStatus::Paid->canTransitionTo(DonationStatus::Refunded))->toBeTrue()
        ->and(DonationStatus::Paid->canTransitionTo(DonationStatus::Pending))->toBeFalse()
        ->and(DonationStatus::Failed->canTransitionTo(DonationStatus::Paid))->toBeFalse()
        ->and(DonationStatus::Refunded->canTransitionTo(DonationStatus::Paid))->toBeFalse();
});

it('keeps ledger entries append-only', function () {
    $donation = Donation::factory()->paid()->create();
    $entry = FundLedgerEntry::create([
        'fund_id' => $donation->fund_id, 'entry_type' => 'credit', 'amount' => '1000.00', 'balance_after' => '1000.00',
        'source_type' => $donation->getMorphClass(), 'source_id' => $donation->id, 'description' => 'Test', 'posted_at' => now(),
    ]);

    expect(fn () => $entry->update(['amount' => '5.00']))->toThrow(DomainException::class, 'never be changed')
        ->and(fn () => $entry->delete())->toThrow(DomainException::class, 'never be deleted');
});

it('allows only one ledger entry per donation', function () {
    $donation = Donation::factory()->paid()->create();
    $row = [
        'fund_id' => $donation->fund_id, 'entry_type' => 'credit', 'amount' => '1000.00', 'balance_after' => '1000.00',
        'source_type' => $donation->getMorphClass(), 'source_id' => $donation->id, 'description' => 'Test', 'posted_at' => now(),
    ];

    FundLedgerEntry::create($row);
    FundLedgerEntry::create($row);
})->throws(QueryException::class);

it('locks a fund\'s type once it has donations (BR-02)', function () {
    $fund = Fund::factory()->zakat()->create();
    Donation::factory()->for($fund)->create();

    $fund->update(['type' => FundType::General]);
})->throws(DomainException::class, 'type cannot change');

it('does not allow deleting a fund or campaign that has donations', function () {
    $admin = staffUser(Role::SuperAdmin);
    $campaign = Campaign::factory()->create();
    Donation::factory()->create(['fund_id' => null, 'campaign_id' => $campaign->id]);

    expect($admin->can('delete', $campaign->fund))->toBeFalse()
        ->and($admin->can('delete', $campaign))->toBeFalse();
});

it('moves donations to the surviving donor when duplicates are merged', function () {
    $survivor = Donor::factory()->create();
    $duplicate = Donor::factory()->create();
    Donation::factory()->count(2)->for($duplicate)->paid()->create();

    app(DonorMergeService::class)->merge($duplicate, $survivor);

    expect($survivor->donations()->count())->toBe(2)
        ->and(Donation::where('donor_id', $duplicate->id)->count())->toBe(0);
});
