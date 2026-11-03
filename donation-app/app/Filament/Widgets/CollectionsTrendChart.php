<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ChecksPermission;
use App\Services\DashboardStats;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/** Money received per day, last 30 days (Week 8 Day 4). */
class CollectionsTrendChart extends ChartWidget
{
    use ChecksPermission;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Received per day — last 30 days';

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return self::userCan('donations.view_any');
    }

    /**
     * Public so tests can read it.
     *
     * @return array{datasets: list<array<string, mixed>>, labels: list<string>}
     */
    public function chartData(): array
    {
        $daily = app(DashboardStats::class)->daily(30);

        return [
            'datasets' => [[
                'label' => 'Rs received',
                'data' => array_map(fn (string $amount): float => (float) $amount, array_values($daily)), // display only
                'borderColor' => '#e5254b',
                'backgroundColor' => 'rgba(229, 37, 75, 0.12)',
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => array_map(fn (string $day): string => CarbonImmutable::parse($day)->format('d M'), array_keys($daily)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        return $this->chartData();
    }

    protected function getType(): string
    {
        return 'line';
    }
}
