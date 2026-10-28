<?php

declare(strict_types=1);

use App\Enums\LedgerEntryType;
use App\Exceptions\InsufficientFundBalance;
use App\Models\Donation;
use App\Models\Fund;
use App\Models\FundLedgerEntry;
use App\Services\FundLedgerService;

// FR-FIN-01, BR-07 — the fund ledger (CLAUDE.md rules 1–3)

function ledger(): FundLedgerService
{
    return app(FundLedgerService::class);
}

/** Any saved record can be a ledger source; a pending donation is the simplest. */
function ledgerSource(Fund $fund): Donation
{
    return Donation::factory()->create(['fund_id' => $fund->id]);
}

it('credits a fund and records the running balance', function () {
    $fund = Fund::factory()->create();
    $source = ledgerSource($fund);

    $entry = ledger()->credit($fund, '1500', $source, 'Test credit');

    expect($entry->entry_type)->toBe(LedgerEntryType::Credit)
        ->and($entry->amount)->toBe('1500.00')
        ->and($entry->balance_after)->toBe('1500.00')
        ->and($entry->source_id)->toBe($source->id)
        ->and($entry->source_type)->toBe($source->getMorphClass())
        ->and($fund->fresh()->balance)->toBe('1500.00');
});

it('keeps a correct running balance across credits and debits', function () {
    $fund = Fund::factory()->create();

    ledger()->credit($fund, '1000.00', ledgerSource($fund), 'In');
    ledger()->debit($fund, '400.00', ledgerSource($fund), 'Out');
    $last = ledger()->credit($fund, '250.50', ledgerSource($fund), 'In');

    expect(FundLedgerEntry::query()->where('fund_id', $fund->id)->orderBy('id')->pluck('balance_after')->all())
        ->toBe(['1000.00', '600.00', '850.50'])
        ->and($last->balance_after)->toBe('850.50')
        ->and($fund->fresh()->balance)->toBe('850.50');
});

it('never lets a fund go below zero (BR-07)', function () {
    $fund = Fund::factory()->create(['name' => 'Ration Fund']);
    ledger()->credit($fund, '1000.00', ledgerSource($fund), 'In');

    expect(fn () => ledger()->debit($fund, '1000.01', ledgerSource($fund), 'Out'))
        ->toThrow(InsufficientFundBalance::class, 'Not enough money in Ration Fund: Rs 1,000.00 available, Rs 1,000.01 needed.');

    expect($fund->fresh()->balance)->toBe('1000.00')
        ->and(FundLedgerEntry::query()->where('fund_id', $fund->id)->count())->toBe(1);
});

it('allows a debit down to exactly zero', function () {
    $fund = Fund::factory()->create();
    ledger()->credit($fund, '750.00', ledgerSource($fund), 'In');

    ledger()->debit($fund, '750', ledgerSource($fund), 'Out');

    expect($fund->fresh()->balance)->toBe('0.00');
});

it('posts each source record only once', function () {
    $fund = Fund::factory()->create();
    $source = ledgerSource($fund);
    ledger()->credit($fund, '500.00', $source, 'First');

    expect(fn () => ledger()->credit($fund, '500.00', $source, 'Again'))
        ->toThrow(DomainException::class, 'already been posted');

    expect($fund->fresh()->balance)->toBe('500.00');
});

it('rejects zero and badly formed amounts', function (string $amount, string $exception) {
    $fund = Fund::factory()->create();

    expect(fn () => ledger()->credit($fund, $amount, ledgerSource($fund), 'Bad'))->toThrow($exception);
    expect($fund->fresh()->balance)->toBe('0.00');
})->with([
    'zero' => ['0', DomainException::class],
    'zero with paisa' => ['0.00', DomainException::class],
    'negative' => ['-50', InvalidArgumentException::class],
    'three decimals' => ['10.999', InvalidArgumentException::class],
    'text' => ['abc', InvalidArgumentException::class],
    'comma' => ['1,000', InvalidArgumentException::class],
]);

it('needs a saved source record', function () {
    $fund = Fund::factory()->create();

    ledger()->credit($fund, '100.00', Donation::factory()->make(['fund_id' => $fund->id]), 'Unsaved');
})->throws(InvalidArgumentException::class, 'saved source');

it('adds paisa exactly, with no rounding drift', function () {
    $fund = Fund::factory()->create();

    foreach (range(1, 10) as $i) {
        ledger()->credit($fund, '0.10', ledgerSource($fund), "Tenth {$i}");
    }

    expect($fund->fresh()->balance)->toBe('1.00')
        ->and(ledger()->balanceFromLedger($fund))->toBe('1.00');
});

it('can prove a fund balance matches its ledger', function () {
    $fund = Fund::factory()->create();
    ledger()->credit($fund, '5000.00', ledgerSource($fund), 'In');
    ledger()->debit($fund, '1250.25', ledgerSource($fund), 'Out');

    expect(ledger()->isBalanced($fund))->toBeTrue();

    // Someone edits the balance behind the ledger's back.
    Fund::query()->whereKey($fund->id)->update(['balance' => '9999.00']);

    expect(ledger()->isBalanced($fund))->toBeFalse();
});
