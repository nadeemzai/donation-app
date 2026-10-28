<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\PaymentMethod;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\DonationRefund;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\FundLedgerEntry;
use App\Models\User;
use App\Services\DonationService;
use App\Services\LedgerAuditService;
use Illuminate\Support\Facades\DB;

// FR-FIN-01, BR-03, BR-05, BR-07 — balances never drift; ledger:verify catches it if they do

function auditor(): LedgerAuditService
{
    return app(LedgerAuditService::class);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function auditDonation(array $overrides = [], bool $paid = true): Donation
{
    $service = app(DonationService::class);
    $by = User::factory()->create();

    $donation = $service->create(array_merge([
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => '1000.00',
        'channel' => DonationChannel::Admin,
        'payment_method' => PaymentMethod::Cash,
    ], $overrides), $by);

    return $paid ? $service->confirm($donation, $by) : $donation;
}

it('keeps every fund, donation and campaign in agreement through 120 random operations', function () {
    mt_srand(20261116); // fixed seed: the same "random" run every time
    $service = app(DonationService::class);

    $funds = Fund::factory()->count(3)->create();
    $campaigns = collect([
        Campaign::factory()->live()->create(['fund_id' => $funds[0]->id]),
        Campaign::factory()->live()->create(['fund_id' => $funds[1]->id]),
    ]);
    $donors = Donor::factory()->count(6)->create();
    $paid = collect();

    foreach (range(1, 120) as $step) {
        $roll = mt_rand(1, 100);

        if ($roll <= 60 || $paid->isEmpty()) {
            // New donation: to a campaign or straight to a fund, then confirmed / cancelled / left pending.
            $campaign = mt_rand(0, 1) === 1 ? $campaigns->random() : null;
            $amount = mt_rand(1, 500000).'.'.str_pad((string) mt_rand(0, 99), 2, '0', STR_PAD_LEFT);
            $donation = auditDonation([
                'donor_id' => $donors->random()->id,
                'fund_id' => $campaign !== null ? null : $funds->random()->id,
                'campaign_id' => $campaign?->id,
                'amount' => $amount,
            ], paid: false);

            $outcome = mt_rand(1, 10);
            if ($outcome <= 7) {
                $paid->push($service->confirm($donation));
            } elseif ($outcome === 8) {
                $service->cancel($donation, 'Random cancel');
            } elseif ($outcome === 9) {
                $service->fail($donation, 'Random fail');
            }
        } else {
            // Refund part (or all) of a random paid donation; some will be refused (BR-07 or nothing left).
            $donation = $paid->random()->fresh();
            $remaining = $donation->netAmount();

            if (bccomp($remaining, '0', 2) === 0) {
                continue;
            }

            $amount = mt_rand(0, 1) === 1 ? $remaining : bcdiv($remaining, (string) mt_rand(2, 5), 2);

            try {
                $service->refund($donation, $amount, 'Random refund', PaymentMethod::BankTransfer);
            } catch (DomainException) {
                // Refused refunds must leave everything unchanged — checked below.
            }
        }
    }

    expect(auditor()->problems())->toBe([])
        ->and(Donation::query()->count())->toBeGreaterThan(50)
        ->and(DonationRefund::query()->count())->toBeGreaterThan(5);

    $this->artisan('ledger:verify')->expectsOutputToContain('Ledger OK')->assertSuccessful();
});

it('reports a fund balance edited behind the ledger\'s back', function () {
    $donation = auditDonation();
    Fund::query()->whereKey($donation->fund_id)->update(['balance' => '999999.00']);

    expect(auditor()->fundProblems())->toHaveCount(1)
        ->and(auditor()->fundProblems()[0])->toContain('stored balance 999999.00, ledger total 1000.00');

    $this->artisan('ledger:verify')->expectsOutputToContain('1 problem(s) found')->assertFailed();
});

it('reports a broken running balance', function () {
    $donation = auditDonation();
    DB::table('fund_ledger_entries')->where('source_id', $donation->id)->update(['balance_after' => '1.00']);

    expect(auditor()->fundProblems())->not->toBe([]);
});

it('reports a paid donation with no ledger credit (BR-03)', function () {
    $donation = Donation::factory()->paid()->create(); // bypasses the service on purpose

    expect(auditor()->donationProblems())->toContain("Donation {$donation->donation_no}: has 0 ledger credits, expected 1.");
});

it('reports a pending donation that has a ledger credit (BR-03)', function () {
    $donation = auditDonation(paid: false);
    FundLedgerEntry::query()->create([
        'fund_id' => $donation->fund_id,
        'entry_type' => 'credit',
        'amount' => '1000.00',
        'balance_after' => '1000.00',
        'source_type' => $donation->getMorphClass(),
        'source_id' => $donation->id,
        'description' => 'Wrong',
        'posted_at' => now(),
    ]);

    expect(auditor()->donationProblems())->toContain("Donation {$donation->donation_no}: is pending but has a ledger credit.");
});

it('reports campaign totals that do not match the donations', function () {
    $campaign = Campaign::factory()->live()->create();
    auditDonation(['fund_id' => null, 'campaign_id' => $campaign->id, 'amount' => '700.00']);
    Campaign::query()->whereKey($campaign->id)->update(['raised_amount' => '50.00', 'donor_count' => 3]);

    expect(auditor()->campaignProblems())->toHaveCount(2);
});

it('reports a refund total that does not match the donation', function () {
    $donation = auditDonation();
    app(DonationService::class)->refund($donation, '400.00', 'Test', PaymentMethod::Cash);
    DB::table('donations')->where('id', $donation->id)->update(['refunded_amount' => '100.00']);

    expect(auditor()->donationProblems())->toContain("Donation {$donation->donation_no}: refunded_amount 100.00, refunds total 400.00.");
});

it('finds nothing wrong on an empty database', function () {
    expect(auditor()->problems())->toBe([]);
    $this->artisan('ledger:verify')->assertSuccessful();
});

it('leaves the status matching the refunds (BR-05)', function () {
    $donation = auditDonation(['amount' => '300.00']);
    app(DonationService::class)->refund($donation, '300.00', 'All back', PaymentMethod::Cash);

    expect($donation->fresh()->status)->toBe(DonationStatus::Refunded)
        ->and(auditor()->problems())->toBe([]);
});
