<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DisbursementStatus;
use App\Enums\DonationStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PayoutMethod;
use App\Models\Campaign;
use App\Models\Disbursement;
use App\Models\Donation;
use App\Models\DonationRefund;
use App\Models\Fund;
use App\Models\FundLedgerEntry;
use App\Reports\ReportTable;
use App\Support\Money;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Finance reports (Week 11 Day 5, FR-RPT-02). Read-only.
 *
 *   collections → money received, grouped by month / day / fund / method / channel / campaign
 *   funds       → per fund: opening, in, out, closing (from the ledger) and payouts still to pay
 *   campaigns   → campaign performance in the period and against the goal
 *   retention   → donors this period vs the period of the same length before it
 *
 * Money received follows the dashboard (BR-03): paid and partly refunded donations by the day
 * they were paid, minus refunds; fully refunded donations are left out. Dates are Pakistan time
 * and both ends of the period are included. Amounts are bcmath decimal strings.
 */
class ReportService
{
    public const REPORTS = [
        'collections' => 'Collections',
        'funds' => 'Disbursements vs collections by fund',
        'campaigns' => 'Campaign performance',
        'retention' => 'Donor retention',
    ];

    public const GROUPS = [
        'month' => 'Month',
        'day' => 'Day',
        'fund' => 'Fund',
        'method' => 'Payment method',
        'channel' => 'Channel',
        'campaign' => 'Campaign',
    ];

    public const MAX_DAYS = 1100; // about three years

    /**
     * @param  array<string, mixed>  $filters  from, until, group, fund_id
     */
    public function build(string $report, array $filters): ReportTable
    {
        [$from, $to] = $this->period($filters['from'] ?? null, $filters['until'] ?? null);
        $fundId = is_numeric($filters['fund_id'] ?? null) ? (int) $filters['fund_id'] : null;
        $group = is_string($filters['group'] ?? null) ? $filters['group'] : 'month';

        return match ($report) {
            'collections' => $this->collections($from, $to, $group, $fundId),
            'funds' => $this->funds($from, $to, $fundId),
            'campaigns' => $this->campaigns($from, $to, $fundId),
            'retention' => $this->retention($from, $to),
            default => throw new DomainException('Choose a report.'),
        };
    }

    public function collections(CarbonImmutable $from, CarbonImmutable $to, string $group = 'month', ?int $fundId = null): ReportTable
    {
        if (! array_key_exists($group, self::GROUPS)) {
            throw new DomainException('Choose how to group the collections.');
        }

        /** @var array<string, array{label: string, sort: string, count: int, gross: string, refunded: string}> $groups */
        $groups = [];
        $totals = ['count' => 0, 'gross' => '0.00', 'refunded' => '0.00'];

        $this->received($from, $to)
            ->when($fundId !== null, fn (Builder $q) => $q->where('fund_id', $fundId))
            ->with(['fund', 'campaign'])
            ->orderBy('paid_at')
            ->lazy(500)
            ->each(function (Donation $donation) use ($group, &$groups, &$totals): void {
                [$key, $label, $sort] = $this->groupOf($donation, $group);

                $groups[$key] ??= ['label' => $label, 'sort' => $sort, 'count' => 0, 'gross' => '0.00', 'refunded' => '0.00'];
                $groups[$key]['count']++;
                $groups[$key]['gross'] = bcadd($groups[$key]['gross'], (string) $donation->amount, 2);
                $groups[$key]['refunded'] = bcadd($groups[$key]['refunded'], (string) $donation->refunded_amount, 2);

                $totals['count']++;
                $totals['gross'] = bcadd($totals['gross'], (string) $donation->amount, 2);
                $totals['refunded'] = bcadd($totals['refunded'], (string) $donation->refunded_amount, 2);
            });

        $byDate = in_array($group, ['month', 'day'], true);

        // Dates in order; everything else largest first.
        uasort($groups, fn (array $a, array $b): int => $byDate
            ? strcmp($a['sort'], $b['sort'])
            : bccomp(bcsub($b['gross'], $b['refunded'], 2), bcsub($a['gross'], $a['refunded'], 2), 2));

        $net = bcsub($totals['gross'], $totals['refunded'], 2);
        $rows = [];

        foreach ($groups as $g) {
            $groupNet = bcsub($g['gross'], $g['refunded'], 2);
            $rows[] = [$g['label'], $g['count'], $g['gross'], $g['refunded'], $groupNet, $this->share($groupNet, $net)];
        }

        return new ReportTable(
            key: 'collections',
            title: 'Collections by '.mb_strtolower(self::GROUPS[$group]).($fundId !== null ? ' — '.$this->fundName($fundId) : ''),
            period: $this->periodLabel($from, $to),
            columns: [
                ['label' => self::GROUPS[$group], 'type' => 'text'],
                ['label' => 'Donations', 'type' => 'int'],
                ['label' => 'Received', 'type' => 'money'],
                ['label' => 'Refunded', 'type' => 'money'],
                ['label' => 'Net', 'type' => 'money'],
                ['label' => 'Share', 'type' => 'percent'],
            ],
            rows: $rows,
            totals: ['Total', $totals['count'], $totals['gross'], $totals['refunded'], $net, $rows === [] ? null : '100'],
            notes: ['Paid and partly refunded donations by the day they were paid; fully refunded donations are left out.'],
        );
    }

    public function funds(CarbonImmutable $from, CarbonImmutable $to, ?int $fundId = null): ReportTable
    {
        $donation = (new Donation)->getMorphClass();
        $refund = (new DonationRefund)->getMorphClass();
        $payout = (new Disbursement)->getMorphClass();

        $funds = Fund::query()->withTrashed()
            ->when($fundId !== null, fn (Builder $q) => $q->whereKey($fundId))
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        $rows = [];
        $sum = array_fill(0, 9, '0.00');

        foreach ($funds as $fund) {
            $opening = $this->balanceAt($fund->id, $from, before: true);
            $closing = $this->balanceAt($fund->id, $to, before: false);

            $moves = FundLedgerEntry::query()
                ->where('fund_id', $fund->id)
                ->whereBetween('posted_at', [$from, $to])
                ->selectRaw('entry_type, source_type, coalesce(sum(amount), 0) as total')
                ->groupBy('entry_type', 'source_type')
                ->toBase()
                ->get();

            $in = $refunds = $paidOut = $otherIn = $otherOut = '0.00';

            foreach ($moves as $move) {
                $total = $this->decimal($move->total ?? '0');
                $credit = $move->entry_type === LedgerEntryType::Credit->value;

                if ($credit && $move->source_type === $donation) {
                    $in = bcadd($in, $total, 2);
                } elseif ($credit) {
                    $otherIn = bcadd($otherIn, $total, 2);
                } elseif ($move->source_type === $refund) {
                    $refunds = bcadd($refunds, $total, 2);
                } elseif ($move->source_type === $payout) {
                    $paidOut = bcadd($paidOut, $total, 2);
                } else {
                    $otherOut = bcadd($otherOut, $total, 2);
                }
            }

            $goods = $this->decimal(Disbursement::query()
                ->where('fund_id', $fund->id)
                ->where('method', PayoutMethod::InKind->value)
                ->where('status', DisbursementStatus::Paid->value)
                ->whereBetween('paid_at', [$from, $to])
                ->sum('amount'));

            $toPay = $this->decimal(Disbursement::query()
                ->where('fund_id', $fund->id)
                ->where('method', '!=', PayoutMethod::InKind->value)
                ->where('status', DisbursementStatus::Approved->value)
                ->sum('amount'));

            $values = [$opening, $in, $refunds, $paidOut, $goods, $otherIn, $otherOut, $closing, $toPay];

            // A fund with no money and no activity adds nothing (unless it was asked for).
            if ($fundId === null && $moves->isEmpty() && array_filter($values, fn (string $v): bool => bccomp($v, '0', 2) !== 0) === []) {
                continue;
            }

            foreach ($values as $i => $v) {
                $sum[$i] = bcadd($sum[$i], $v, 2);
            }

            $rows[] = [$fund->name.($fund->is_zakat ? ' (Zakat)' : '').($fund->trashed() ? ' — archived' : ''), ...$values];
        }

        return new ReportTable(
            key: 'funds',
            title: 'Disbursements vs collections by fund',
            period: $this->periodLabel($from, $to),
            columns: [
                ['label' => 'Fund', 'type' => 'text'],
                ['label' => 'Opening balance', 'type' => 'money'],
                ['label' => 'Donations in', 'type' => 'money'],
                ['label' => 'Refunds out', 'type' => 'money'],
                ['label' => 'Disbursed (money)', 'type' => 'money'],
                ['label' => 'Goods given (value)', 'type' => 'money'],
                ['label' => 'Other in', 'type' => 'money'],
                ['label' => 'Other out', 'type' => 'money'],
                ['label' => 'Closing balance', 'type' => 'money'],
                ['label' => 'Approved, not yet paid', 'type' => 'money'],
            ],
            rows: $rows,
            totals: $rows === [] ? null : ['Total', ...$sum],
            notes: [
                'Balances and money in / out come from the fund ledger: opening + in − out = closing.',
                'Goods given are in-kind disbursements at their estimated value; they do not move money.',
                '"Approved, not yet paid" is as of now, not the end of the period.',
            ],
        );
    }

    public function campaigns(CarbonImmutable $from, CarbonImmutable $to, ?int $fundId = null): ReportTable
    {
        /** @var array<int, array{count: int, net: string, donors: array<int, true>}> $stats */
        $stats = [];

        $this->received($from, $to)
            ->whereNotNull('campaign_id')
            ->when($fundId !== null, fn (Builder $q) => $q->where('fund_id', $fundId))
            ->select(['id', 'campaign_id', 'donor_id', 'amount', 'refunded_amount'])
            ->lazy(500)
            ->each(function (Donation $donation) use (&$stats): void {
                $id = (int) $donation->campaign_id;
                $stats[$id] ??= ['count' => 0, 'net' => '0.00', 'donors' => []];
                $stats[$id]['count']++;
                $stats[$id]['net'] = bcadd($stats[$id]['net'], bcsub((string) $donation->amount, (string) $donation->refunded_amount, 2), 2);
                $stats[$id]['donors'][$donation->donor_id] = true;
            });

        // Campaigns that received money in the period, or were live during it.
        $campaigns = Campaign::query()->withTrashed()->with('fund')
            ->when($fundId !== null, fn (Builder $q) => $q->where('fund_id', $fundId))
            ->where(fn (Builder $q) => $q->whereIn('id', array_keys($stats))
                ->orWhere(fn (Builder $live) => $live->whereNull('deleted_at')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', $to)
                    ->where(fn (Builder $d) => $d->whereNull('closed_at')->orWhere('closed_at', '>=', $from))))
            ->get();

        $rows = [];
        $sum = ['count' => 0, 'net' => '0.00', 'raised' => '0.00'];

        foreach ($campaigns as $campaign) {
            $s = $stats[$campaign->id] ?? ['count' => 0, 'net' => '0.00', 'donors' => []];
            $raised = (string) $campaign->raised_amount;
            $goal = $campaign->goal_amount !== null && bccomp((string) $campaign->goal_amount, '0', 2) > 0 ? (string) $campaign->goal_amount : null;

            $rows[] = [
                $campaign->title.($campaign->trashed() ? ' — archived' : ''),
                $campaign->fund->name,
                $campaign->status->label(),
                $s['count'],
                count($s['donors']),
                $s['net'],
                $s['count'] > 0 ? bcdiv($s['net'], (string) $s['count'], 2) : null,
                $raised,
                $goal,
                $goal !== null ? bcdiv(bcmul($raised, '100', 4), $goal, 1) : null,
            ];

            $sum['count'] += $s['count'];
            $sum['net'] = bcadd($sum['net'], $s['net'], 2);
            $sum['raised'] = bcadd($sum['raised'], $raised, 2);
        }

        usort($rows, fn (array $a, array $b): int => bccomp((string) $b[5], (string) $a[5], 2) ?: strcmp((string) $a[0], (string) $b[0]));

        return new ReportTable(
            key: 'campaigns',
            title: 'Campaign performance'.($fundId !== null ? ' — '.$this->fundName($fundId) : ''),
            period: $this->periodLabel($from, $to),
            columns: [
                ['label' => 'Campaign', 'type' => 'text'],
                ['label' => 'Fund', 'type' => 'text'],
                ['label' => 'Status', 'type' => 'text'],
                ['label' => 'Donations', 'type' => 'int'],
                ['label' => 'Donors', 'type' => 'int'],
                ['label' => 'Raised in period', 'type' => 'money'],
                ['label' => 'Average gift', 'type' => 'money'],
                ['label' => 'Raised to date', 'type' => 'money'],
                ['label' => 'Goal', 'type' => 'money'],
                ['label' => 'Of goal', 'type' => 'percent'],
            ],
            rows: $rows,
            totals: $rows === [] ? null : ['Total', null, null, $sum['count'], null, $sum['net'], $sum['count'] > 0 ? bcdiv($sum['net'], (string) $sum['count'], 2) : null, $sum['raised'], null, null],
            notes: ['Campaigns that were live in the period or received money in it. "Raised to date" and "Of goal" are all-time.'],
        );
    }

    public function retention(CarbonImmutable $from, CarbonImmutable $to): ReportTable
    {
        $days = (int) $from->diffInDays($to->startOfDay()) + 1;
        $prevTo = $from->subDay()->endOfDay();
        $prevFrom = $prevTo->subDays($days - 1)->startOfDay();

        $now = $this->donorsBetween($from, $to);
        $before = $this->donorsBetween($prevFrom, $prevTo);

        // Donors whose first ever gift is in this period.
        $new = $now === [] ? 0 : Donation::query()
            ->whereIn('status', [DonationStatus::Paid->value, DonationStatus::PartiallyRefunded->value])
            ->whereIn('donor_id', $now)
            ->where('paid_at', '<', $from)
            ->distinct()
            ->count('donor_id');
        $new = count($now) - $new;

        $retained = count(array_intersect($now, $before));
        $lapsed = count($before) - $retained;
        $returning = count($now) - $new - $retained; // gave before, skipped the last period, came back

        $collected = $this->decimal($this->received($from, $to)->selectRaw('coalesce(sum(amount), 0) - coalesce(sum(refunded_amount), 0) as n')->toBase()->value('n'));
        $gifts = $this->received($from, $to)->count();

        $rows = [
            ['Donors who gave in this period', number_format(count($now))],
            ['— first-time donors', number_format($new)],
            ['— also gave in the period before (retained)', number_format($retained)],
            ['— came back after a gap', number_format($returning)],
            ['Donors in the period before ('.$this->periodLabel($prevFrom, $prevTo).')', number_format(count($before))],
            ['— did not give again this period (lapsed)', number_format($lapsed)],
            ['Retention rate', $before === [] ? '—' : ReportTable::percent(bcdiv(bcmul((string) $retained, '100', 4), (string) count($before), 1))],
            ['Collected this period', Money::rupees($collected)],
            ['Average gift', $gifts > 0 ? Money::rupees(bcdiv($collected, (string) $gifts, 2)) : '—'],
            ['Average given per donor', $now !== [] ? Money::rupees(bcdiv($collected, (string) count($now), 2)) : '—'],
        ];

        return new ReportTable(
            key: 'retention',
            title: 'Donor retention',
            period: $this->periodLabel($from, $to),
            columns: [['label' => 'Measure', 'type' => 'text'], ['label' => 'Value', 'type' => 'text']],
            rows: $rows,
            notes: ['Compared with the period of the same length just before. Retention rate = donors of the period before who gave again ÷ all donors of the period before.'],
        );
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function period(mixed $from, mixed $until): array
    {
        try {
            $start = CarbonImmutable::parse(is_string($from) && $from !== '' ? $from : now()->startOfMonth()->toDateString())->startOfDay();
            $end = CarbonImmutable::parse(is_string($until) && $until !== '' ? $until : now()->toDateString())->endOfDay();
        } catch (Throwable) {
            throw new DomainException('Enter the dates as day, month and year.');
        }

        if ($start->greaterThan($end)) {
            throw new DomainException('The start date is after the end date.');
        }

        if ($start->diffInDays($end) > self::MAX_DAYS) {
            throw new DomainException('Choose a period of three years or less.');
        }

        return [$start, $end];
    }

    public function periodLabel(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return $from->isSameDay($to) ? $from->format('d M Y') : $from->format('d M Y').' – '.$to->format('d M Y');
    }

    /**
     * @return Builder<Donation>
     */
    private function received(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Donation::query()
            ->whereIn('status', [DonationStatus::Paid->value, DonationStatus::PartiallyRefunded->value])
            ->whereBetween('paid_at', [$from, $to]);
    }

    /**
     * @return list<int>
     */
    private function donorsBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->received($from, $to)->distinct()->pluck('donor_id')->map(fn (mixed $id): int => (int) $id)->values()->all();
    }

    /**
     * @return array{0: string, 1: string, 2: string} key, label, sort key
     */
    private function groupOf(Donation $donation, string $group): array
    {
        $paid = $donation->paid_at ?? now();

        return match ($group) {
            'day' => [$paid->format('Y-m-d'), $paid->format('d M Y (D)'), $paid->format('Y-m-d')],
            'month' => [$paid->format('Y-m'), $paid->format('F Y'), $paid->format('Y-m')],
            'fund' => ['f'.$donation->fund_id, $donation->fund->name, ''],
            'method' => [$donation->payment_method->value, $donation->payment_method->label(), ''],
            'channel' => [$donation->channel->value, $donation->channel->getLabel(), ''],
            default => $donation->campaign !== null
                ? ['c'.$donation->campaign_id, $donation->campaign->title, '']
                : ['none', 'No campaign (general giving)', ''],
        };
    }

    private function balanceAt(int $fundId, CarbonImmutable $at, bool $before): string
    {
        $entry = FundLedgerEntry::query()
            ->where('fund_id', $fundId)
            ->where('posted_at', $before ? '<' : '<=', $at)
            ->orderByDesc('posted_at')
            ->orderByDesc('id')
            ->first();

        return $entry !== null ? (string) $entry->balance_after : '0.00';
    }

    private function share(string $part, string $whole): ?string
    {
        return bccomp($whole, '0', 2) > 0 ? bcdiv(bcmul($part, '100', 4), $whole, 1) : null;
    }

    private function fundName(int $fundId): string
    {
        $name = Fund::query()->withTrashed()->whereKey($fundId)->value('name');

        return is_string($name) ? $name : 'Fund';
    }

    /** SUM comes back as "12500", "12500.00", an int or a float depending on the database. */
    private function decimal(mixed $value): string
    {
        return match (true) {
            is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', $value) === 1 => bcadd($value, '0', 2),
            is_int($value), is_float($value) => number_format((float) $value, 2, '.', ''),
            default => '0.00',
        };
    }
}
