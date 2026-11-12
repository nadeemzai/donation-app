<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SettlementMatch;
use App\Filament\Resources\Settlements\Pages\CreateSettlement;
use App\Filament\Resources\Settlements\Pages\ViewSettlement;
use App\Filament\Resources\Settlements\RelationManagers\LinesRelationManager;
use App\Models\FundLedgerEntry;
use App\Models\Payment;
use App\Models\Settlement;
use App\Models\SettlementLine;
use App\Services\SettlementService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

// W11-D4 — FR-FIN-02: gateway settlement CSV import, matching and the mismatch report.

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(now()->setTime(12, 0));
});

function settlements(): SettlementService
{
    return app(SettlementService::class);
}

/** A payment that succeeded here today. */
function settledHere(PaymentMethod $method = PaymentMethod::JazzCash, ?string $gatewayRef = null, string $status = 'succeeded'): Payment
{
    $payment = Payment::factory()->create(['method' => $method]);
    $payment->forceFill([
        'status' => PaymentStatus::from($status),
        'gateway_ref' => $gatewayRef ?? 'GW-'.$payment->id,
        'completed_at' => $status === 'succeeded' ? now()->subHour() : null,
    ])->save();

    return $payment->refresh();
}

/**
 * @param  list<list<string|int>>  $rows
 */
function settlementCsv(array $rows, array $header = ['Txn Ref No', 'Transaction ID', 'Amount', 'Fee', 'Status', 'Date']): string
{
    return collect([$header, ...$rows])->map(fn (array $r): string => implode(',', array_map(fn ($c): string => str_contains((string) $c, ',') ? '"'.$c.'"' : (string) $c, $r)))->implode("\n");
}

function importToday(string $csv, PaymentMethod $gateway = PaymentMethod::JazzCash): Settlement
{
    return settlements()->import($gateway, now(), $csv, 'settlement.csv', by: staffUser(Role::Finance));
}

describe('import and matching', function () {
    it('matches rows to payments by our reference or the gateway id', function () {
        $a = settledHere();
        $b = settledHere();

        $settlement = importToday(settlementCsv([
            [$a->reference, $a->gateway_ref, $a->amount, '25.00', 'Success', now()->format('Y-m-d H:i:s')],
            ['', $b->gateway_ref, 'Rs '.number_format((float) $b->amount, 2), '10', 'Success', now()->format('d/m/Y')],
        ]));

        expect($settlement->row_count)->toBe(2)
            ->and($settlement->matched_count)->toBe(2)
            ->and($settlement->problem_count)->toBe(0)
            ->and($settlement->lines->pluck('payment_id')->all())->toBe([$a->id, $b->id])
            ->and((string) $settlement->fee_total)->toBe('35.00')
            ->and((string) $settlement->net_total)->toBe(bcsub(bcadd((string) $a->amount, (string) $b->amount, 2), '35', 2))
            ->and(Storage::disk('local')->exists($settlement->file_path))->toBeTrue()
            ->and(Activity::query()->where('event', 'settlement_imported')->exists())->toBeTrue();
    });

    it('flags every kind of mismatch', function () {
        $short = settledHere();
        $pending = settledHere(status: 'pending');
        $other = settledHere(PaymentMethod::Easypaisa);
        $twice = settledHere();

        $settlement = importToday(settlementCsv([
            [$short->reference, '', bcsub((string) $short->amount, '1', 2), '0', 'Success', ''],
            [$pending->reference, '', $pending->amount, '0', 'Success', ''],
            [$other->reference, '', $other->amount, '0', 'Success', ''],
            ['NOT-OURS-1', 'X1', '500', '0', 'Success', ''],
            [$twice->reference, '', $twice->amount, '0', 'Success', ''],
            [$twice->reference, '', $twice->amount, '0', 'Success', ''],
            ['', '', '100', '0', 'Success', ''],
            [$short->reference, '', 'abc', '0', 'Success', ''],
            ['FAILED-1', '', '700', '0', 'Failed', ''],
        ]));

        expect($settlement->lines->map(fn (SettlementLine $l): SettlementMatch => $l->match_status)->all())->toBe([
            SettlementMatch::AmountMismatch,
            SettlementMatch::NotSucceeded,
            SettlementMatch::WrongGateway,
            SettlementMatch::NotFound,
            SettlementMatch::Matched,
            SettlementMatch::Duplicate,
            SettlementMatch::Unreadable,
            SettlementMatch::Unreadable,
            SettlementMatch::Ignored,
        ])
            ->and($settlement->matched_count)->toBe(1)
            ->and($settlement->problem_count)->toBe(7)
            ->and($settlement->ignored_count)->toBe(1);
    });

    it('lists our payments of the day that the file does not settle', function () {
        $in = settledHere();
        $missing = settledHere();
        settledHere(PaymentMethod::Easypaisa); // other gateway — not expected in a JazzCash file

        $settlement = importToday(settlementCsv([[$in->reference, '', $in->amount, '0', 'Success', '']]));

        expect(settlements()->missingFrom($settlement)->pluck('id')->all())->toBe([$missing->id]);

        $csv = settlements()->mismatchCsv($settlement);
        expect($csv)->toContain('Paid in our system but not in this file')->toContain($missing->reference);
    });

    it('reads paisa amounts, semicolons, a BOM and title lines above the header', function () {
        config()->set('settlements.amounts_in_paisa.jazzcash', true);
        $payment = settledHere();

        $csv = "\xEF\xBB\xBFMerchant settlement report\nGenerated today\n"
            ."pp_TxnRefNo;pp_Amount;pp_ResponseCode\n"
            ."{$payment->reference};".bcmul((string) $payment->amount, '100', 0).";000\n";

        expect(importToday($csv)->matched_count)->toBe(1);
    });

    it('refuses the same file twice, unknown columns, other methods and future dates', function () {
        $csv = settlementCsv([[settledHere()->reference, '', '100', '0', 'Success', '']]);
        importToday($csv);

        expect(fn () => importToday($csv))->toThrow(DomainException::class, 'already imported')
            ->and(fn () => importToday("Name,Phone\nAli,0300"))->toThrow(DomainException::class, 'Could not find the columns')
            ->and(fn () => importToday(''))->toThrow(DomainException::class, 'empty')
            ->and(fn () => settlements()->import(PaymentMethod::Cash, now(), $csv.' ', 'x.csv'))->toThrow(DomainException::class, 'JazzCash or Easypaisa')
            ->and(fn () => settlements()->import(PaymentMethod::JazzCash, now()->addDay(), $csv.' ', 'x.csv'))->toThrow(DomainException::class, 'future')
            ->and(Settlement::query()->count())->toBe(1);
    });

    it('never touches payments or the ledger', function () {
        $pending = settledHere(status: 'pending');
        $ledger = FundLedgerEntry::query()->count();

        importToday(settlementCsv([[$pending->reference, '', $pending->amount, '0', 'Success', '']]));

        expect($pending->fresh()->status)->toBe(PaymentStatus::Pending)
            ->and(FundLedgerEntry::query()->count())->toBe($ledger);
    });
});

describe('after import', function () {
    it('matches again once our payment is confirmed', function () {
        $late = settledHere(status: 'pending');
        $settlement = importToday(settlementCsv([[$late->reference, '', $late->amount, '0', 'Success', '']]));
        expect($settlement->problem_count)->toBe(1);

        $late->forceFill(['status' => PaymentStatus::Succeeded, 'completed_at' => now()])->save();
        $settlement = settlements()->rematch($settlement, staffUser(Role::Finance));

        expect($settlement->matched_count)->toBe(1)
            ->and($settlement->problem_count)->toBe(0)
            ->and(SettlementLine::query()->sole()->payment_id)->toBe($late->id);
    });

    it('resolves a flagged row with a note, keeping the flag in history', function () {
        $settlement = importToday(settlementCsv([['NOT-OURS', '', '500', '0', 'Success', '']]));
        $line = $settlement->lines()->sole();

        expect(fn () => settlements()->resolve($line, ' ', staffUser(Role::Finance)))->toThrow(DomainException::class, 'how');

        settlements()->resolve($line, 'Test payment by staff', staffUser(Role::Finance));

        expect($line->refresh()->match_status)->toBe(SettlementMatch::NotFound)
            ->and($line->resolved_at)->not->toBeNull()
            ->and($settlement->refresh()->problem_count)->toBe(0)
            ->and(fn () => settlements()->resolve($line, 'Again', staffUser(Role::Finance)))->toThrow(DomainException::class, 'still open');
    });

    it('keeps files and rows immutable', function () {
        $settlement = importToday(settlementCsv([['NOT-OURS', '', '500', '0', 'Success', '']]));
        $line = $settlement->lines()->sole();

        expect(fn () => $settlement->update(['settlement_date' => now()->subDay()]))->toThrow(DomainException::class)
            ->and(fn () => $settlement->delete())->toThrow(DomainException::class)
            ->and(fn () => $line->update(['amount' => '1']))->toThrow(DomainException::class)
            ->and(fn () => $line->delete())->toThrow(DomainException::class);
    });
});

describe('screens', function () {
    it('imports a file from the screen', function () {
        $payment = settledHere();
        $csv = settlementCsv([[$payment->reference, '', $payment->amount, '0', 'Success', '']]);

        Livewire::actingAs(staffUser(Role::Finance))->test(CreateSettlement::class)
            ->fillForm([
                'gateway' => 'jazzcash',
                'settlement_date' => now()->toDateString(),
                'file' => UploadedFile::fake()->createWithContent('jc-settlement.csv', $csv),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Settlement::query()->sole())
            ->file_name->toBe('jc-settlement.csv')
            ->matched_count->toBe(1);
    });

    it('shows the report and resolves a row from the view page', function () {
        $user = staffUser(Role::Finance);
        $settlement = importToday(settlementCsv([['NOT-OURS', '', '500', '0', 'Success', '']]));
        $line = $settlement->lines()->sole();

        $this->actingAs($user)->get(route('filament.admin.resources.settlements.view', $settlement))
            ->assertOk()->assertSee('No such payment');

        Livewire::actingAs($user)->test(LinesRelationManager::class, ['ownerRecord' => $settlement, 'pageClass' => ViewSettlement::class])
            ->assertCanSeeTableRecords([$line])
            ->callAction(TestAction::make('resolve')->table($line), ['note' => 'Not our merchant account'])
            ->assertHasNoActionErrors();

        expect($line->refresh()->resolution_note)->toBe('Not our merchant account');

        Livewire::actingAs($user)->test(ViewSettlement::class, ['record' => $settlement->getRouteKey()])
            ->callAction('mismatchReport')
            ->assertFileDownloaded();
    });

    it('lets view-only roles see but not resolve', function () {
        $settlement = importToday(settlementCsv([['NOT-OURS', '', '500', '0', 'Success', '']]));

        Livewire::actingAs(staffUser(Role::Trustee))->test(LinesRelationManager::class, ['ownerRecord' => $settlement, 'pageClass' => ViewSettlement::class])
            ->assertActionHidden(TestAction::make('resolve')->table($settlement->lines()->sole()));
    });
});
