<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ChecksPermission;
use App\Services\DashboardStats;
use Filament\Widgets\Widget;

/** The five campaigns that raised most this month, with progress to goal (Week 8 Day 4). */
class TopCampaigns extends Widget
{
    use ChecksPermission;

    protected static ?int $sort = 6;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.top-campaigns';

    public static function canView(): bool
    {
        return self::userCan('donations.view_any', 'campaigns.view_any');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['rows' => app(DashboardStats::class)->topCampaigns(today()->startOfMonth(), today()->endOfDay())];
    }
}
