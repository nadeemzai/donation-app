<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ChecksPermission;
use App\Services\DashboardStats;
use Filament\Widgets\Widget;

/** This month by payment method — JazzCash, Easypaisa, bank, cash, cheque (Week 8 Day 4). */
class CollectionsByMethod extends Widget
{
    use ChecksPermission;

    protected static ?int $sort = 5;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.collections-by-method';

    public static function canView(): bool
    {
        return self::userCan('donations.view_any');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['rows' => app(DashboardStats::class)->byMethod(today()->startOfMonth(), today()->endOfDay())];
    }
}
