<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ChecksPermission;
use App\Models\RecurringPlan;
use App\Services\DashboardStats;
use App\Support\FiscalYear;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Money received today, this month and this tax year, and what pledges promise each month
 * (Week 8 Day 4, FR-RPT-01). Only confirmed money counts (BR-03), net of refunds.
 */
class CollectionsOverview extends StatsOverviewWidget
{
    use ChecksPermission;

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return self::userCan('donations.view_any');
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $stats = app(DashboardStats::class);
        $today = $stats->today();
        $month = $stats->thisMonth();
        $last = $stats->lastMonthSoFar();
        $year = $stats->thisTaxYear();
        $change = self::change($month['amount'], $last['amount']);

        return [
            Stat::make('Received today', Money::rupees($today['amount']))
                ->description(trans_choice(':count donation|:count donations', $today['count'], ['count' => $today['count']]))
                ->icon(Heroicon::OutlinedBanknotes),
            Stat::make('This month', Money::rupees($month['amount']))
                ->description($change === null ? $month['count'].' donations' : $month['count'].' donations · '.$change.' vs last month')
                ->descriptionIcon($change !== null && str_starts_with($change, '−') ? Heroicon::ArrowTrendingDown : Heroicon::ArrowTrendingUp)
                ->color($change !== null && str_starts_with($change, '−') ? 'warning' : 'success')
                ->icon(Heroicon::OutlinedCalendar),
            Stat::make('Tax year '.FiscalYear::containing(now())->label(), Money::rupees($year['amount']))
                ->description($year['count'].' donations since 1 July')
                ->icon(Heroicon::OutlinedChartBar),
            Stat::make('Pledged each month', Money::rupees($stats->pledgedPerMonth()))
                ->description(RecurringPlan::query()->active()->count().' active pledges')
                ->icon(Heroicon::OutlinedArrowPathRoundedSquare),
        ];
    }

    /** "+12%" / "−5%" against the same days last month; null when last month had nothing. */
    private static function change(string $now, string $before): ?string
    {
        if (bccomp($before, '0', 2) <= 0) {
            return null;
        }

        $percent = (int) round((float) bcmul(bcdiv(bcsub($now, $before, 2), $before, 4), '100', 2));

        return ($percent < 0 ? '−' : '+').abs($percent).'%';
    }
}
