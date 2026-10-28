<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Donor;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

/**
 * Dashboard headline numbers (FR-RPT-01, first version). Donation figures by
 * fund and gateway arrive with donations in Week 3.
 */
class CampaignStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user instanceof User && ($user->can('campaigns.view_any') || $user->can('donors.view_any'));
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $live = Campaign::query()->live();
        $raised = (string) (clone $live)->sum('raised_amount');
        $endingSoon = (clone $live)
            ->whereNotNull('ends_at')
            ->whereDate('ends_at', '>=', now()->toDateString())
            ->whereDate('ends_at', '<=', now()->addDays(7)->toDateString())
            ->count();

        return [
            Stat::make('Live campaigns', (string) (clone $live)->count())
                ->description(Campaign::query()->where('status', CampaignStatus::Draft->value)->count().' in draft')
                ->icon(Heroicon::OutlinedMegaphone),
            Stat::make('Raised in live campaigns', 'Rs '.number_format((float) $raised))
                ->icon(Heroicon::OutlinedBanknotes),
            Stat::make('Donors', number_format(Donor::query()->count()))
                ->description(number_format(Donor::query()->whereNotNull('password')->count()).' with an account')
                ->icon(Heroicon::OutlinedUserGroup),
            Stat::make('Ending in 7 days', (string) $endingSoon)
                ->description($endingSoon > 0 ? 'Post an update or extend the date' : 'Nothing ending soon')
                ->color($endingSoon > 0 ? 'warning' : 'gray')
                ->icon(Heroicon::OutlinedClock),
        ];
    }
}
