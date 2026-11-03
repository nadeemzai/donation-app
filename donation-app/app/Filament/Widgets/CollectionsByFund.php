<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ChecksPermission;
use App\Services\DashboardStats;
use Filament\Widgets\Widget;

/** This month by fund, with each fund's balance for those allowed to see the ledger (Week 8 Day 4). */
class CollectionsByFund extends Widget
{
    use ChecksPermission;

    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.collections-by-fund';

    public static function canView(): bool
    {
        return self::userCan('donations.view_any');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'rows' => app(DashboardStats::class)->byFund(today()->startOfMonth(), today()->endOfDay()),
            'showBalance' => self::userCan('ledger.view_any'),
        ];
    }
}
