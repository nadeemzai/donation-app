<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The admin panel's colours (Imdaad brand + one bright colour per area). Used by the panel
 * stylesheet (resources/views/filament/admin-theme.blade.php), dashboard cards and charts,
 * so every screen uses the same set.
 */
final class AdminPalette
{
    /** Brand crimson — the website's primary colour. */
    public const CRIMSON = '#e5254b';

    /** name => [from, to] gradient for stat cards. */
    public const TONES = [
        'crimson' => ['#e5254b', '#be123c'],
        'emerald' => ['#10b981', '#047857'],
        'sky' => ['#0ea5e9', '#1d4ed8'],
        'amber' => ['#f59e0b', '#ea580c'],
        'violet' => ['#8b5cf6', '#6d28d9'],
        'teal' => ['#14b8a6', '#0f766e'],
        'pink' => ['#ec4899', '#be185d'],
        'red' => ['#ef4444', '#b91c1c'],
        'slate' => ['#64748b', '#334155'],
    ];

    /** Solid colours for charts, bars and dots, in order. */
    public const SERIES = ['#e5254b', '#10b981', '#0ea5e9', '#f59e0b', '#8b5cf6', '#14b8a6', '#ec4899', '#6366f1', '#84cc16', '#f97316'];

    /** Colour of each sidebar group's icons. */
    public const GROUPS = [
        'Dashboard' => '#fda4af',
        'Donations' => '#6ee7b7',
        'Campaigns' => '#fcd34d',
        'Donors' => '#7dd3fc',
        'Programs' => '#c4b5fd',
        'Seasonal' => '#5eead4',
        'Inventory' => '#fdba74',
        'Finance' => '#86efac',
        'Content' => '#f9a8d4',
        'Settings' => '#cbd5e1',
    ];

    /** The nth series colour, repeating. */
    public static function series(int $index): string
    {
        return self::SERIES[$index % count(self::SERIES)];
    }

    /** "#e5254b" + 0.15 → "rgba(229, 37, 75, 0.15)". */
    public static function alpha(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');

        return sprintf('rgba(%d, %d, %d, %s)', hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)), $alpha);
    }

    /**
     * Classes that turn a dashboard stat card into a coloured gradient card.
     *
     * @return array{class: string}
     */
    public static function stat(string $tone): array
    {
        return ['class' => 'imd-stat imd-stat-'.(array_key_exists($tone, self::TONES) ? $tone : 'slate')];
    }
}
