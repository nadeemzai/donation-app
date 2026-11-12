<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Events\DonationPaid;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\FundLedgerEntry;
use App\Models\User;
use App\Services\CampaignService;
use App\Services\DonationService;
use App\Services\FundLedgerService;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;

// FR-DON-01/02, BR-01, BR-03, BR-09 — creating and settling donations

function donations(): DonationService
{
    return app(DonationService::class);
}

/**
 * A cash donation entered by Finance in the admin panel.
 *
 * @param  array<string, mixed>  $overrides
 */
function newDonation(array $overrides = [], ?User $by = null): Donation
{
    return donations()->create(array_merge([
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => '2500.00',
        'channel' => DonationChannel::Admin,
        'payment_method' => PaymentMethod::Cash,
    ], $overrides), $by ?? User::factory()->create());
}

// ── create ──────────────────────────────────────────────────────────────

it('records a pending donation that is not counted yet (BR-03)', function () {
    $campaign = Campaign::factory()->live()->create();

    $donation = newDonation(['fund_id' => null, 'campaign_id' => $campaign->id]);

    expect($donation->fresh()->status)->toBe(DonationStatus::Pending)
        ->and($donation->fund_id)->toBe($campaign->fund_id)
        ->and($donation->received_at)->not->toBeNull()
        ->and($campaign->fund->fresh()->balance)->toBe('0.00')
        ->and($campaign->fresh()->raised_amount)->toBe('0.00')
        ->and(FundLedgerEntry::query()->count())->toBe(0);
});

it('records which staff member entered an admin donation', function () {
    $finance = staffUser(Role::Finance);

    expect(newDonation(by: $finance)->recorded_by)->toBe($finance->id);
});

it('refuses an admin donation with nobody recorded as entering it', function () {
    donations()->create([
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => '1000.00',
        'channel' => DonationChannel::Admin,
        'payment_method' => PaymentMethod::Cash,
    ]);
})->throws(DomainException::class, 'must record who entered it');

it('accepts a website donation without a staff user', function () {
    $donation = donations()->create([
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => '1000.00',
        'channel' => DonationChannel::Web,
        'payment_method' => PaymentMethod::JazzCash,
    ]);

    expect($donation->recorded_by)->toBeNull()
        ->and($donation->received_at)->toBeNull();
});

it('refuses donations to an inactive fund', function () {
    newDonation(['fund_id' => Fund::factory()->inactive()->create(['name' => 'Old Appeal'])->id]);
})->throws(DomainException::class, 'The Old Appeal fund is not taking donations');

it('refuses donations from an archived donor', function () {
    $donor = Donor::factory()->create();
    $donor->delete();

    newDonation(['donor_id' => $donor->id]);
})->throws(DomainException::class, 'Donor not found');

it('refuses donations to a draft campaign', function () {
    $campaign = Campaign::factory()->create();

    newDonation(['fund_id' => null, 'campaign_id' => $campaign->id]);
})->throws(DomainException::class, 'not live yet');

it('refuses a campaign donation aimed at another fund (BR-01)', function () {
    $campaign = Campaign::factory()->live()->create();

    newDonation(['fund_id' => Fund::factory()->create()->id, 'campaign_id' => $campaign->id]);
})->throws(DomainException::class, 'go to its own fund only');

it('sends a donation for a closed campaign to its fund (BR-09)', function () {
    $campaign = Campaign::factory()->closed()->create();

    $donation = newDonation(['fund_id' => null, 'campaign_id' => $campaign->id]);

    expect($donation->campaign_id)->toBeNull()
        ->and($donation->fund_id)->toBe($campaign->fund_id);

    $log = Activity::query()->where('event', 'redirected_to_fund')->sole();
    expect($log->subject_id)->toBe($donation->id)
        ->and(data_get($log->properties, 'campaign_id'))->toBe($campaign->id);
});

it('treats a campaign past its end date as closed, even before the nightly close runs (BR-09)', function () {
    $campaign = Campaign::factory()->live()->create([
        'starts_at' => now()->subDays(20)->toDateString(),
        'ends_at' => now()->subDay()->toDateString(),
    ]);

    $donation = newDonation(['fund_id' => null, 'campaign_id' => $campaign->id]);

    expect($donation->campaign_id)->toBeNull()
        ->and($donation->fund_id)->toBe($campaign->fund_id);
});

it('returns the original donation when the same request is retried', function () {
    $donor = Donor::factory()->create();
    $fund = Fund::factory()->create();
    $request = [
        'donor_id' => $donor->id,
        'fund_id' => $fund->id,
        'amount' => '3000.00',
        'channel' => DonationChannel::Mobile,
        'payment_method' => PaymentMethod::Easypaisa,
        'idempotency_key' => 'mob-7f3c2a',
    ];

    $first = donations()->create($request);
    $again = donations()->create($request);

    expect($again->id)->toBe($first->id)
        ->and(Donation::query()->count())->toBe(1);
});

it('refuses a reused request key with a different amount', function () {
    $request = [
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => '3000.00',
        'channel' => DonationChannel::Mobile,
        'payment_method' => PaymentMethod::Easypaisa,
        'idempotency_key' => 'mob-7f3c2a',
    ];
    donations()->create($request);

    donations()->create(['amount' => '9000.00'] + $request);
})->throws(DomainException::class, 'already used for a different donation');

// ── confirm ─────────────────────────────────────────────────────────────

it('confirms a donation: credits the fund and the campaign (BR-03)', function () {
    Event::fake([DonationPaid::class]);
    $finance = staffUser(Role::Finance);
    $campaign = Campaign::factory()->live()->create();
    $donation = newDonation(['fund_id' => null, 'campaign_id' => $campaign->id, 'amount' => '5000.00'], $finance);

    $paid = donations()->confirm($donation, $finance, 'CHQ-004512');

    expect($paid->status)->toBe(DonationStatus::Paid)
        ->and($paid->paid_at)->not->toBeNull()
        ->and($paid->confirmed_by)->toBe($finance->id)
        ->and($paid->reference)->toBe('CHQ-004512')
        ->and($campaign->fund->fresh()->balance)->toBe('5000.00')
        ->and($campaign->fresh()->raised_amount)->toBe('5000.00')
        ->and($campaign->fresh()->donor_count)->toBe(1);

    $entry = FundLedgerEntry::query()->sole();
    expect($entry->entry_type)->toBe(LedgerEntryType::Credit)
        ->and($entry->amount)->toBe('5000.00')
        ->and($entry->source_id)->toBe($donation->id)
        ->and($entry->posted_by)->toBe($finance->id)
        ->and($entry->description)->toBe("Donation {$donation->donation_no}");

    Event::assertDispatched(DonationPaid::class, fn (DonationPaid $e): bool => $e->donation->id === $donation->id);
});

it('logs who confirmed a donation', function () {
    $finance = staffUser(Role::Finance);
    $donation = newDonation(by: $finance);

    donations()->confirm($donation, $finance);

    $log = Activity::query()->where('event', 'confirmed')->sole();
    expect($log->subject_id)->toBe($donation->id)
        ->and($log->causer_id)->toBe($finance->id)
        ->and(data_get($log->properties, 'amount'))->toBe('2500.00');
});

it('confirms only once, even if called twice (repeated webhook)', function () {
    Event::fake([DonationPaid::class]);
    $donation = newDonation(['amount' => '1000.00']);

    donations()->confirm($donation);
    $again = donations()->confirm($donation);

    expect($again->status)->toBe(DonationStatus::Paid)
        ->and($donation->fund->fresh()->balance)->toBe('1000.00')
        ->and(FundLedgerEntry::query()->count())->toBe(1);

    Event::assertDispatchedTimes(DonationPaid::class, 1);
});

it('counts each donor once on a campaign', function () {
    $campaign = Campaign::factory()->live()->create();
    $donor = Donor::factory()->create();

    foreach (['1000.00', '1500.00'] as $amount) {
        donations()->confirm(newDonation(['fund_id' => null, 'campaign_id' => $campaign->id, 'donor_id' => $donor->id, 'amount' => $amount]));
    }
    donations()->confirm(newDonation(['fund_id' => null, 'campaign_id' => $campaign->id, 'amount' => '500.00']));

    expect($campaign->fresh()->raised_amount)->toBe('3000.00')
        ->and($campaign->fresh()->donor_count)->toBe(2);
});

it('does not count pending, failed or cancelled donations', function () {
    $campaign = Campaign::factory()->live()->create();
    newDonation(['fund_id' => null, 'campaign_id' => $campaign->id]);
    donations()->fail(newDonation(['fund_id' => null, 'campaign_id' => $campaign->id]));
    donations()->cancel(newDonation(['fund_id' => null, 'campaign_id' => $campaign->id]));

    expect($campaign->fresh()->raised_amount)->toBe('0.00')
        ->and($campaign->fresh()->donor_count)->toBe(0)
        ->and($campaign->fund->fresh()->balance)->toBe('0.00');
});

it('still counts a donation started before the campaign closed', function () {
    $campaign = Campaign::factory()->live()->create();
    $donation = newDonation(['fund_id' => null, 'campaign_id' => $campaign->id, 'amount' => '4000.00']);

    app(CampaignService::class)->close($campaign, 'Goal reached');
    donations()->confirm($donation);

    expect($campaign->fresh()->raised_amount)->toBe('4000.00')
        ->and($campaign->fund->fresh()->balance)->toBe('4000.00');
});

it('cannot confirm a failed or cancelled donation', function (string $method) {
    $donation = newDonation();
    donations()->{$method}($donation);

    donations()->confirm($donation);
})->with(['fail', 'cancel'])->throws(DomainException::class, 'cannot become Paid');

it('leaves everything unchanged when the ledger posting fails', function () {
    $donation = newDonation(['amount' => '1200.00']);

    // Simulate a broken state: a ledger line for this donation already exists.
    FundLedgerEntry::query()->create([
        'fund_id' => $donation->fund_id,
        'entry_type' => LedgerEntryType::Credit,
        'amount' => '1.00',
        'balance_after' => '0.00',
        'source_type' => $donation->getMorphClass(),
        'source_id' => $donation->id,
        'description' => 'Stray line',
        'posted_at' => now(),
    ]);

    expect(fn () => donations()->confirm($donation))->toThrow(DomainException::class, 'already been posted');

    expect($donation->fresh()->status)->toBe(DonationStatus::Pending)
        ->and($donation->fresh()->paid_at)->toBeNull()
        ->and($donation->fund->fresh()->balance)->toBe('0.00');
});

it('keeps every fund balance equal to its ledger after many donations', function () {
    $funds = Fund::factory()->count(3)->create();

    foreach (range(1, 15) as $i) {
        $fund = $funds[$i % 3];
        $donation = newDonation(['fund_id' => $fund->id, 'amount' => (string) ($i * 100).'.25']);
        donations()->confirm($donation);
    }

    foreach ($funds as $fund) {
        expect(app(FundLedgerService::class)->isBalanced($fund))->toBeTrue();
    }

    expect(bcadd(bcadd((string) $funds[0]->fresh()->balance, (string) $funds[1]->fresh()->balance, 2), (string) $funds[2]->fresh()->balance, 2))
        ->toBe('12003.75'); // 100+200+…+1500 = 12000, plus 15 × 0.25
});

// ── fail / cancel ───────────────────────────────────────────────────────

it('marks a donation failed without touching the ledger', function () {
    $donation = newDonation();

    $failed = donations()->fail($donation, 'Wallet declined');

    expect($failed->status)->toBe(DonationStatus::Failed)
        ->and($failed->failed_at)->not->toBeNull()
        ->and(FundLedgerEntry::query()->count())->toBe(0)
        ->and(data_get(Activity::query()->where('event', 'failed')->sole()->properties, 'reason'))->toBe('Wallet declined');
});

it('cancels a pending donation', function () {
    $cancelled = donations()->cancel(newDonation(), 'Donor changed their mind');

    expect($cancelled->status)->toBe(DonationStatus::Cancelled)
        ->and($cancelled->cancelled_at)->not->toBeNull();
});

it('ignores a repeated fail or cancel', function () {
    $donation = newDonation();
    $first = donations()->fail($donation);
    $again = donations()->fail($donation);

    expect($again->failed_at?->toDateTimeString())->toBe($first->failed_at?->toDateTimeString())
        ->and(Activity::query()->where('event', 'failed')->count())->toBe(1);
});

it('cannot fail or cancel a paid donation (BR-05)', function (string $method) {
    $donation = newDonation();
    donations()->confirm($donation);

    donations()->{$method}($donation);
})->with(['fail', 'cancel'])->throws(DomainException::class, 'Paid donation cannot become');
