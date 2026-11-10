<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Events\DonationPaid;
use App\Filament\Pages\Reports;
use App\Models\Campaign;
use App\Models\Disbursement;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Reports\ReportExporter;
use App\Reports\ReportTable;
use App\Services\DonationService;
use App\Services\FundLedgerService;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Activitylog\Models\Activity;
use Spatie\LaravelPdf\Facades\Pdf;

// W11-D5 — FR-RPT-02: collections, disbursements vs collections by fund, campaign performance,
// donor retention; Excel / PDF export.

const REPORT_NOW = '2027-03-15 12:00:00';

beforeEach(function () {
    Event::fake([DonationPaid::class]);
    $this->travelTo(Carbon::parse(REPORT_NOW));
});

function reports(): ReportService
{
    return app(ReportService::class);
}

/** A donation confirmed (through the ledger) at $at. */
function gave(string $amount, string $at, ?Fund $fund = null, ?Campaign $campaign = null, ?Donor $donor = null, PaymentMethod $method = PaymentMethod::Cash): Donation
{
    Carbon::setTestNow(Carbon::parse($at));

    $donation = Donation::factory()->create(array_filter([
        'amount' => $amount,
        'fund_id' => $campaign?->fund_id ?? $fund?->id,
        'campaign_id' => $campaign?->id,
        'donor_id' => $donor?->id,
        'payment_method' => $method,
    ], fn (mixed $v): bool => $v !== null));

    $donation = app(DonationService::class)->confirm($donation, paidAt: Carbon::parse($at));
    Carbon::setTestNow(Carbon::parse(REPORT_NOW));

    return $donation;
}

function march(): array
{
    return [CarbonImmutable::parse('2027-03-01')->startOfDay(), CarbonImmutable::parse('2027-03-31')->endOfDay()];
}

/** @return array<string, list<string|int|null>> rows keyed by their first cell */
function rowsByLabel(ReportTable $report): array
{
    return collect($report->rows)->keyBy(fn (array $r): string => (string) $r[0])->all();
}

describe('collections', function () {
    it('totals what was received in the period, minus refunds, by month / fund / method', function () {
        $general = Fund::factory()->create(['name' => 'General']);
        $zakat = Fund::factory()->zakat()->create(['name' => 'Zakat']);

        gave('1000', '2027-02-28 23:00', $general);             // before the period
        gave('5000', '2027-03-02 10:00', $general);
        gave('3000', '2027-03-10 10:00', $zakat, method: PaymentMethod::JazzCash);
        $partly = gave('2000', '2027-03-11 10:00', $general);
        app(DonationService::class)->refund($partly, '500', 'Duplicate', PaymentMethod::Cash);
        $full = gave('4000', '2027-03-12 10:00', $general);
        app(DonationService::class)->refund($full, '4000', 'Wrong fund', PaymentMethod::Cash); // left out

        [$from, $to] = march();

        $byMonth = reports()->collections($from, $to, 'month');
        expect($byMonth->rows)->toHaveCount(1)
            ->and($byMonth->rows[0])->toBe(['March 2027', 3, '10000.00', '500.00', '9500.00', '100.0'])
            ->and($byMonth->totals)->toBe(['Total', 3, '10000.00', '500.00', '9500.00', '100']);

        $byFund = rowsByLabel(reports()->collections($from, $to, 'fund'));
        expect($byFund['General'][4])->toBe('6500.00')
            ->and($byFund['Zakat'][4])->toBe('3000.00')
            ->and(array_key_first($byFund))->toBe('General'); // largest first

        expect(rowsByLabel(reports()->collections($from, $to, 'method'))['JazzCash'][1])->toBe(1)
            ->and(reports()->collections($from, $to, 'month', $zakat->id)->totals[4])->toBe('3000.00');
    });

    it('groups by day in date order and names general giving', function () {
        gave('100', '2027-03-05 09:00');
        gave('200', '2027-03-03 09:00');

        [$from, $to] = march();
        $report = reports()->collections($from, $to, 'day');

        expect(array_column($report->rows, 0))->toBe(['03 Mar 2027 (Wed)', '05 Mar 2027 (Fri)'])
            ->and(reports()->collections($from, $to, 'campaign')->rows[0][0])->toBe('No campaign (general giving)');
    });
});

describe('funds', function () {
    it('shows opening, money in and out, and closing from the ledger', function () {
        $fund = Fund::factory()->create(['name' => 'Education']);
        Fund::factory()->create(['name' => 'Quiet fund']); // nothing ever happened — left out

        gave('10000', '2027-02-10 10:00', $fund);                 // opening 10,000
        gave('5000', '2027-03-05 10:00', $fund);
        $refunded = gave('2000', '2027-03-06 10:00', $fund);

        Carbon::setTestNow(Carbon::parse('2027-03-07 10:00'));
        app(DonationService::class)->refund($refunded, '1000', 'Asked back', PaymentMethod::Cash);
        app(FundLedgerService::class)->debit($fund, '3000', Disbursement::factory()->create(['fund_id' => $fund->id]), 'School fees');
        Carbon::setTestNow(Carbon::parse(REPORT_NOW));

        gave('700', '2027-04-02 10:00', $fund); // after the period — not counted

        [$from, $to] = march();
        $report = reports()->funds($from, $to);
        $row = rowsByLabel($report)['Education'];

        //            opening     in          refunds    disbursed  goods   other in other out closing     to pay
        expect(array_slice($row, 1))->toBe(['10000.00', '7000.00', '1000.00', '3000.00', '0.00', '0.00', '0.00', '13000.00', '0.00'])
            ->and(rowsByLabel($report))->not->toHaveKey('Quiet fund')
            ->and($report->totals[8])->toBe('13000.00');
    });
});

describe('campaigns', function () {
    it('shows money raised in the period, donors, average gift and progress to goal', function () {
        $water = Campaign::factory()->live()->create(['title' => 'Clean Water', 'goal_amount' => '100000']);
        $school = Campaign::factory()->live()->create(['title' => 'School Fees', 'goal_amount' => null]);
        $donor = Donor::factory()->create();

        gave('20000', '2027-02-20 10:00', campaign: $water);   // before the period: counts to date only
        gave('10000', '2027-03-03 10:00', campaign: $water, donor: $donor);
        gave('5000', '2027-03-04 10:00', campaign: $water, donor: $donor);
        gave('1000', '2027-03-05 10:00', campaign: $school);

        [$from, $to] = march();
        $report = reports()->campaigns($from, $to);
        $rows = rowsByLabel($report);

        expect(array_key_first($rows))->toBe('Clean Water')
            ->and(array_slice($rows['Clean Water'], 3))->toBe([2, 1, '15000.00', '7500.00', '35000.00', '100000.00', '35.0'])
            ->and($rows['School Fees'][8])->toBeNull()
            ->and($report->totals[5])->toBe('16000.00');
    });
});

describe('retention', function () {
    it('compares donors with the period of the same length before', function () {
        [$kept, $lapsed, $back] = Donor::factory()->count(3)->create()->all();
        $newcomer = Donor::factory()->create();

        gave('100', '2026-12-10 10:00', donor: $back);    // gave long ago, skipped February
        gave('100', '2027-02-05 10:00', donor: $kept);    // the period before = 29 Jan – 28 Feb
        gave('100', '2027-02-06 10:00', donor: $lapsed);
        gave('300', '2027-03-03 10:00', donor: $kept);
        gave('500', '2027-03-04 10:00', donor: $newcomer);
        gave('400', '2027-03-05 10:00', donor: $back);

        [$from, $to] = march();
        $rows = rowsByLabel(reports()->retention($from, $to));

        expect($rows['Donors who gave in this period'][1])->toBe('3')
            ->and($rows['— first-time donors'][1])->toBe('1')
            ->and($rows['— also gave in the period before (retained)'][1])->toBe('1')
            ->and($rows['— came back after a gap'][1])->toBe('1')
            ->and($rows['— did not give again this period (lapsed)'][1])->toBe('1')
            ->and($rows['Retention rate'][1])->toBe('50%')
            ->and($rows['Collected this period'][1])->toBe('Rs 1,200.00');
    });
});

it('refuses a period that ends before it starts or is too long', function () {
    expect(fn () => reports()->build('collections', ['from' => '2027-03-10', 'until' => '2027-03-01']))->toThrow(DomainException::class, 'after the end')
        ->and(fn () => reports()->build('collections', ['from' => '2020-01-01', 'until' => '2027-03-01']))->toThrow(DomainException::class, 'three years')
        ->and(fn () => reports()->build('nope', []))->toThrow(DomainException::class, 'Choose a report');
});

describe('export', function () {
    it('writes Excel with numbers as numbers', function () {
        gave('5000', '2027-03-02 10:00');
        [$from, $to] = march();

        $path = ReportExporter::excel(reports()->collections($from, $to));

        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();
        @unlink($path);

        $header = collect($rows)->search(fn (array $r): bool => ($r[0] ?? null) === 'Month');

        expect($header)->not->toBeFalse()
            ->and($rows[$header])->toBe(['Month', 'Donations', 'Received', 'Refunded', 'Net', 'Share'])
            ->and($rows[$header + 1][0])->toBe('March 2027')
            ->and($rows[$header + 1][2])->toEqual(5000.0)
            ->and($rows[$header + 2][0])->toBe('Total');
    });

    it('renders the PDF from the report view', function () {
        Pdf::fake();
        gave('5000', '2027-03-02 10:00');
        [$from, $to] = march();

        ReportExporter::pdf(reports()->collections($from, $to));

        Pdf::assertViewIs('pdf.report');
        Pdf::assertSee(['Collections by month', '01 Mar 2027 – 31 Mar 2027', 'Rs 5,000.00']);
    });
});

describe('the reports screen', function () {
    it('shows a report and exports it, audit-logged', function () {
        gave('5000', '2027-03-02 10:00', Fund::factory()->create(['name' => 'Health']));

        Livewire::actingAs(staffUser(Role::Finance))->test(Reports::class)
            ->fillForm(['report' => 'funds', 'from' => '2027-03-01', 'until' => '2027-03-31'])
            ->assertSee('Disbursements vs collections by fund')
            ->assertSee('Health')
            ->callAction('excel')
            ->assertFileDownloaded();

        expect(Activity::query()->where('event', 'exported')->where('description', 'like', 'Report exported to Excel%')->exists())->toBeTrue();
    });

    it('fills the dates from a preset', function () {
        Livewire::actingAs(staffUser(Role::Finance))->test(Reports::class)
            ->fillForm(['preset' => 'last_month'])
            ->assertFormSet(['from' => '2027-02-01', 'until' => '2027-02-28']);
    });

    it('shows a clear message for a bad period', function () {
        Livewire::actingAs(staffUser(Role::Finance))->test(Reports::class)
            ->fillForm(['from' => '2027-03-10', 'until' => '2027-03-01'])
            ->assertSee('The start date is after the end date.');
    });

    it('lets roles without export only view', function () {
        Livewire::actingAs(staffUser(Role::CampaignManager))->test(Reports::class)
            ->assertOk()
            ->assertActionHidden('excel')
            ->assertActionHidden('pdf');
    });
});
