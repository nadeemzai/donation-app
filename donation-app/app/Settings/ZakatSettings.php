<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Zakat calculator rates (Week 6 Day 3). Updated by website staff in
 * Admin → Website → Zakat rates. Rupees per tola; null until first set.
 */
class ZakatSettings extends Settings
{
    public ?int $gold_rate_per_tola;

    public ?int $silver_rate_per_tola;

    /** "silver" (52.5 tola) or "gold" (7.5 tola). */
    public string $nisab_basis;

    /** Y-m-d — shown to donors so they know how fresh the rates are. */
    public ?string $rates_date;

    public static function group(): string
    {
        return 'zakat';
    }
}
