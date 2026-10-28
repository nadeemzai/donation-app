<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Prefixes for public document numbers, e.g. DN-2026-000001 (BR-04).
 */
class NumberingSettings extends Settings
{
    public string $donation_prefix;

    public string $receipt_prefix;

    public string $disbursement_prefix;

    public string $donor_prefix;

    public int $pad_length;

    public static function group(): string
    {
        return 'numbering';
    }
}
