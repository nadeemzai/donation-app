<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Events\DonationRefunded;
use App\Exceptions\InsufficientFundBalance;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\DonationRefund;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\FundLedgerEntry;
use App\Models\User;
use App\Services\DonationService;
use App\Services\FundLedgerService;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;

// FR-DON-02, FR-FIN-01, BR-05, BR-07 — refunds and reversals

/**
 * A confirmed cash donation, credited to its fund (and campaign, if given).
 *
 * @param  array<string, mixed>  $overrides
 */
function paidDonation(array $overrides = []): Donation
{
    $service = app(DonationService::class);
    $by = User::factory()->create();

    $donation = $service->create(array_merge([
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => '5000.00',
        'channel' => DonationChannel::Admin,
        'payment_method' => PaymentMethod::Cash,
    ], $overrides), $by);

    return $service->confirm($donation, $by);
}

function refundOf(Donation $donation, string $amount, ?User $by = null): DonationRefund
{
    return app(DonationService::class)->refund($donation, $amount, 'Donor asked for the money back', PaymentMethod::BankTransfer, $by, 'IBFT-88412');
}

it('fully refunds a donation and reverses every total', function () {
    Event::fake([DonationRefunded::class]);
    $campaign = Campaign::factory()->live()->create();
    $donation = paidDonation(['fund_id' => null, 'campaign_id' => $campaign->id]);
    $finance = User::factory()->create();

    $refund = refundOf($donation, '5000', $finance);

    $donation->refresh();
    expect($donation->status)->toBe(DonationStatus::Refunded)
        ->and($donation->refunded_amount)->toBe('5000.00')
        ->and($donation->amount)->toBe('5000.00') // BR-05: the original amount is kept
        ->and($donation->netAmount())->toBe('0.00')
        ->and($campaign->fund->fresh()->balance)->toBe('0.00')
        ->and($campaign->fresh()->raised_amount)->toBe('0.00')
        ->and($campaign->fresh()->donor_count)->toBe(0);

    expect($refund->amount)->toBe('5000.00')
        ->and($refund->fund_id)->toBe($donation->fund_id)
        ->and($refund->method)->toBe(PaymentMethod::BankTransfer)
        ->and($refund->reference)->toBe('IBFT-88412')
        ->and($refund->refunded_by)->toBe($finance->id);

    $debit = $refund->ledgerEntry;
    expect($debit)->not->toBeNull()
        ->and($debit?->entry_type)->toBe(LedgerEntryType::Debit)
        ->and($debit?->amount)->toBe('5000.00')
        ->and($debit?->balance_after)->toBe('0.00')
        ->and($debit?->description)->toBe("Refund on donation {$donation->donation_no}");

    Event::assertDispatched(DonationRefunded::class, fn (DonationRefunded $e): bool => $e->refund->id === $refund->id);
});

it('partly refunds a donation', function () {
    $campaign = Campaign::factory()->live()->create();
    $donation = paidDonation(['fund_id' => null, 'campaign_id' => $campaign->id]);

    refundOf($donation, '1500.00');

    $donation->refresh();
    expect($donation->status)->toBe(DonationStatus::PartiallyRefunded)
        ->and($donation->netAmount())->toBe('3500.00')
        ->and($campaign->fund->fresh()->balance)->toBe('3500.00')
        ->and($campaign->fresh()->raised_amount)->toBe('3500.00')
        ->and($campaign->fresh()->donor_count)->toBe(1);
});

it('becomes fully refunded after several partial refunds', function () {
    $campaign = Campaign::factory()->live()->create();
    $donation = paidDonation(['fund_id' => null, 'campaign_id' => $campaign->id]);

    refundOf($donation, '2000.00');
    refundOf($donation, '2999.50');
    refundOf($donation, '0.50');

    $donation->refresh();
    expect($donation->status)->toBe(DonationStatus::Refunded)
        ->and($donation->refunds()->count())->toBe(3)
        ->and($campaign->fund->fresh()->balance)->toBe('0.00')
        ->and($campaign->fresh()->donor_count)->toBe(0)
        ->and(FundLedgerEntry::query()->where('entry_type', LedgerEntryType::Debit)->count())->toBe(3);
});

it('cannot refund more than what is left', function () {
    $donation = paidDonation();
    refundOf($donation, '3500.00');

    refundOf($donation, '1500.01');
})->throws(DomainException::class, 'Only Rs 1,500.00 of');

it('only refunds paid donations', function (Closure $make) {
    refundOf($make(), '100.00');
})->with([
    'pending' => fn () => Donation::factory()->create(),
    'failed' => fn () => app(DonationService::class)->fail(Donation::factory()->create()),
    'cancelled' => fn () => app(DonationService::class)->cancel(Donation::factory()->create()),
    'already refunded' => function () {
        $donation = paidDonation(['amount' => '100.00']);
        refundOf($donation, '100.00');

        return $donation;
    },
])->throws(DomainException::class, 'cannot be refunded');

it('needs a reason', function () {
    app(DonationService::class)->refund(paidDonation(), '100.00', '   ', PaymentMethod::Cash);
})->throws(DomainException::class, 'Give a reason');

it('rejects zero and badly formed refund amounts', function (string $amount, string $exception) {
    $donation = paidDonation();

    expect(fn () => refundOf($donation, $amount))->toThrow($exception);
    expect($donation->fresh()->status)->toBe(DonationStatus::Paid);
})->with([
    'zero' => ['0.00', DomainException::class],
    'negative' => ['-100', InvalidArgumentException::class],
    'three decimals' => ['10.555', InvalidArgumentException::class],
]);

it('refuses a refund the fund can no longer cover, and changes nothing (BR-07)', function () {
    $donation = paidDonation(['amount' => '5000.00']);

    // The fund has since paid out 4,000 for a programme (stands in for a disbursement).
    app(FundLedgerService::class)->debit($donation->fund_id, '4000.00', Donation::factory()->create(['fund_id' => $donation->fund_id]), 'Programme spend');

    expect(fn () => refundOf($donation, '5000.00'))->toThrow(InsufficientFundBalance::class, 'Rs 1,000.00 available');

    expect($donation->fresh()->status)->toBe(DonationStatus::Paid)
        ->and($donation->fresh()->refunded_amount)->toBe('0.00')
        ->and(DonationRefund::query()->count())->toBe(0)
        ->and($donation->fund->fresh()->balance)->toBe('1000.00');
});

it('keeps counting a donor who still has another gift in the campaign', function () {
    $campaign = Campaign::factory()->live()->create();
    $donor = Donor::factory()->create();
    $first = paidDonation(['fund_id' => null, 'campaign_id' => $campaign->id, 'donor_id' => $donor->id, 'amount' => '1000.00']);
    paidDonation(['fund_id' => null, 'campaign_id' => $campaign->id, 'donor_id' => $donor->id, 'amount' => '2000.00']);

    refundOf($first, '1000.00');

    expect($campaign->fresh()->raised_amount)->toBe('2000.00')
        ->and($campaign->fresh()->donor_count)->toBe(1);
});

it('refunds a donation that went straight to a fund', function () {
    $donation = paidDonation(['amount' => '800.00']);

    refundOf($donation, '800.00');

    expect($donation->fresh()->status)->toBe(DonationStatus::Refunded)
        ->and($donation->fund->fresh()->balance)->toBe('0.00');
});

it('logs every refund with who made it and why', function () {
    $finance = User::factory()->create();
    $donation = paidDonation();

    refundOf($donation, '1200.00', $finance);

    $log = Activity::query()->where('event', 'refunded')->sole();
    expect($log->subject_id)->toBe($donation->id)
        ->and($log->causer_id)->toBe($finance->id)
        ->and(data_get($log->properties, 'amount'))->toBe('1200.00')
        ->and(data_get($log->properties, 'reason'))->toBe('Donor asked for the money back')
        ->and($log->description)->toBe("Refund of Rs 1,200.00 on donation {$donation->donation_no}");
});

it('never changes or deletes a refund record', function (string $action) {
    $refund = refundOf(paidDonation(), '100.00');

    $action === 'update' ? $refund->update(['amount' => '1.00']) : $refund->delete();
})->with(['update', 'delete'])->throws(DomainException::class, 'can never be');

it('keeps the paid donation\'s facts frozen after a refund (BR-05)', function () {
    $donation = paidDonation();
    refundOf($donation, '1000.00');

    $donation->fresh()?->update(['amount' => '4000.00']);
})->throws(DomainException::class, 'cannot be changed');

it('keeps the fund balance equal to its ledger through payments and refunds', function () {
    $fund = Fund::factory()->create();

    $a = paidDonation(['fund_id' => $fund->id, 'amount' => '10000.00']);
    $b = paidDonation(['fund_id' => $fund->id, 'amount' => '2500.75']);
    refundOf($a, '3333.33');
    refundOf($b, '2500.75');
    paidDonation(['fund_id' => $fund->id, 'amount' => '99.99']);

    expect($fund->fresh()->balance)->toBe('6766.66') // 10000 + 2500.75 − 3333.33 − 2500.75 + 99.99
        ->and(app(FundLedgerService::class)->isBalanced($fund))->toBeTrue();
});
