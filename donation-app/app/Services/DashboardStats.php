<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DonationStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PledgePeriodStatus;
use App\Enums\PledgeStatus;
use App\Models\Campaign;
use App\Models\ContactMessage;
use App\Models\Donation;
use App\Models\Fund;
use App\Models\Payment;
use App\Models\RecurringPlan;
use App\Support\FiscalYear;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Numbers for the admin dashboard (Week 8 Day 4, FR-RPT-01).
 *
 * Money counts only what was received (BR-03): paid and partly refunded donations, by the
 * day they were paid, minus refunds. Days, months and tax years are Pakistan time.
 * Read-only; amounts are decimal strings (bcmath), never floats.
 */
class DashboardStats
{
    /**
     * @return array{amount: string, count: int}
     */
    public function collected(CarbonInterface $from, CarbonInterface $to): array
    {
        $row = $this->paidBetween($from, $to)
            ->selectRaw('count(*) as c, coalesce(sum(amount), 0) as a, coalesce(sum(refunded_amount), 0) as r')
            ->toBase()
            ->first();

        return [
            'amount' => self::net($row->a ?? '0', $row->r ?? '0'),
            'count' => (int) ($row->c ?? 0),
        ];
    }

    /** @return array{amount: string, count: int} */
    public function today(): array
    {
        return $this->collected(today(), today()->endOfDay());
    }

    /** @return array{amount: string, count: int} */
    public function thisMonth(): array
    {
        return $this->collected(today()->startOfMonth(), today()->endOfDay());
    }

    /** @return array{amount: string, count: int} */
    public function lastMonthSoFar(): array
    {
        // Same days of last month, for a fair "vs last month".
        $start = today()->startOfMonth()->subMonthNoOverflow();
        $end = $start->copy()->addDays(today()->day - 1)->endOfDay();
        $monthEnd = $start->copy()->endOfMonth();

        return $this->collected($start, $end->gt($monthEnd) ? $monthEnd : $end);
    }

    /** @return array{amount: string, count: int} */
    public function thisTaxYear(): array
    {
        $year = FiscalYear::containing(now());

        return $this->collected($year->start(), today()->endOfDay());
    }

    /**
     * This month by fund, biggest first. Funds with nothing this month are listed too.
     *
     * @return list<array{fund: string, code: string, is_zakat: bool, amount: string, count: int, balance: string}>
     */
    public function byFund(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->paidBetween($from, $to)
            ->selectRaw('fund_id, count(*) as c, coalesce(sum(amount), 0) as a, coalesce(sum(refunded_amount), 0) as r')
            ->groupBy('fund_id')
            ->toBase()
            ->get()
            ->keyBy('fund_id');

        $out = Fund::query()->active()->get()->map(function (Fund $fund) use ($rows): array {
            $row = $rows->get($fund->id);

            return [
                'fund' => $fund->name,
                'code' => $fund->code,
                'is_zakat' => $fund->is_zakat,
                'amount' => $row !== null ? self::net($row->a, $row->r) : '0.00',
                'count' => $row !== null ? (int) $row->c : 0,
                'balance' => (string) $fund->balance,
            ];
        })->all();

        usort($out, fn (array $a, array $b): int => bccomp($b['amount'], $a['amount'], 2) ?: strcmp($a['fund'], $b['fund']));

        return $out;
    }

    /**
     * By payment method, with each one's share of the total.
     *
     * @return list<array{method: PaymentMethod, amount: string, count: int, share: int}>
     */
    public function byMethod(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->paidBetween($from, $to)
            ->selectRaw('payment_method, count(*) as c, coalesce(sum(amount), 0) as a, coalesce(sum(refunded_amount), 0) as r')
            ->groupBy('payment_method')
            ->toBase()
            ->get();

        $total = '0.00';
        $out = [];

        foreach ($rows as $row) {
            $method = PaymentMethod::tryFrom((string) $row->payment_method);

            if ($method === null) {
                continue;
            }

            $amount = self::net($row->a, $row->r);
            $total = bcadd($total, $amount, 2);
            $out[] = ['method' => $method, 'amount' => $amount, 'count' => (int) $row->c, 'share' => 0];
        }

        foreach ($out as $i => $line) {
            $out[$i]['share'] = bccomp($total, '0', 2) > 0 ? (int) round((float) bcdiv(bcmul($line['amount'], '100', 2), $total, 2)) : 0;
        }

        usort($out, fn (array $a, array $b): int => bccomp($b['amount'], $a['amount'], 2));

        return $out;
    }

    /**
     * The campaigns that raised most in the period, with progress towards their goal.
     *
     * @return list<array{campaign: Campaign, amount: string, count: int}>
     */
    public function topCampaigns(CarbonInterface $from, CarbonInterface $to, int $limit = 5): array
    {
        $rows = $this->paidBetween($from, $to)
            ->whereNotNull('campaign_id')
            ->selectRaw('campaign_id, count(*) as c, coalesce(sum(amount), 0) as a, coalesce(sum(refunded_amount), 0) as r')
            ->groupBy('campaign_id')
            ->toBase()
            ->get();

        $campaigns = Campaign::withTrashed()->with('fund')->whereIn('id', $rows->pluck('campaign_id')->all())->get()->keyBy('id');
        $out = [];

        foreach ($rows as $row) {
            $campaign = $campaigns->get($row->campaign_id);

            if ($campaign instanceof Campaign) {
                $out[] = ['campaign' => $campaign, 'amount' => self::net($row->a, $row->r), 'count' => (int) $row->c];
            }
        }

        usort($out, fn (array $a, array $b): int => bccomp($b['amount'], $a['amount'], 2));

        return array_slice($out, 0, $limit);
    }

    /**
     * Collected per day for the last $days days (oldest first), for the trend chart.
     *
     * @return array<string, string> Y-m-d => amount
     */
    public function daily(int $days = 30): array
    {
        $from = today()->subDays($days - 1);
        $series = [];

        for ($d = CarbonImmutable::instance($from); $d->lte(today()); $d = $d->addDay()) {
            $series[$d->toDateString()] = '0.00';
        }

        $this->paidBetween($from, today()->endOfDay())
            ->get(['paid_at', 'amount', 'refunded_amount'])
            ->each(function (Donation $donation) use (&$series): void {
                $day = $donation->paid_at?->toDateString();

                if ($day !== null && isset($series[$day])) {
                    $series[$day] = bcadd($series[$day], $donation->netAmount(), 2);
                }
            });

        return $series;
    }

    /**
     * Things waiting for someone.
     *
     * @return array{proofs: int, payments_review: int, pending_old: int, pledges_overdue: int, messages: int}
     */
    public function queues(): array
    {
        return [
            // Bank transfers / cheques with a proof file, waiting for Finance.
            'proofs' => Donation::query()
                ->where('status', DonationStatus::Pending->value)
                ->whereIn('payment_method', [PaymentMethod::BankTransfer->value, PaymentMethod::Cheque->value])
                ->whereHas('media', fn (Builder $q) => $q->where('collection_name', Donation::PROOF))
                ->count(),
            'payments_review' => Payment::query()->where('status', PaymentStatus::NeedsReview->value)->count(),
            // Pending for more than a day with no proof: likely abandoned or stuck.
            'pending_old' => Donation::query()
                ->where('status', DonationStatus::Pending->value)
                ->where('created_at', '<', now()->subDay())
                ->whereDoesntHave('media', fn (Builder $q) => $q->where('collection_name', Donation::PROOF))
                ->count(),
            'pledges_overdue' => RecurringPlan::query()
                ->where('status', PledgeStatus::Active->value)
                ->where(fn (Builder $q) => $q
                    ->whereDate('next_due_on', '<', today())
                    ->orWhereHas('periods', fn (Builder $p) => $p->where('status', PledgePeriodStatus::Due->value)->whereDate('due_on', '<', today())))
                ->count(),
            'messages' => ContactMessage::query()->unhandled()->count(),
        ];
    }

    /** What active pledges add up to per month (quarterly ÷ 3, yearly ÷ 12). */
    public function pledgedPerMonth(): string
    {
        $total = '0.00';

        RecurringPlan::query()->where('status', PledgeStatus::Active->value)->get(['amount', 'frequency'])
            ->each(function (RecurringPlan $plan) use (&$total): void {
                $total = bcadd($total, bcdiv((string) $plan->amount, (string) $plan->frequency->months(), 2), 2);
            });

        return $total;
    }

    /**
     * @return Builder<Donation>
     */
    private function paidBetween(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Donation::query()
            ->whereIn('status', [DonationStatus::Paid->value, DonationStatus::PartiallyRefunded->value])
            ->whereBetween('paid_at', [$from, $to]);
    }

    /** SUM can come back as "12500", "12500.00" or a float-ish string depending on the database. */
    private static function net(mixed $amount, mixed $refunded): string
    {
        $clean = fn (mixed $v): string => match (true) {
            is_string($v) && preg_match('/^-?\d+(\.\d+)?$/', $v) === 1 => bcadd($v, '0', 2),
            is_int($v), is_float($v) => number_format((float) $v, 2, '.', ''),
            default => '0.00',
        };

        return bcsub($clean($amount), $clean($refunded), 2);
    }
}
