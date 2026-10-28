<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Organisation details shown on receipts, certificates and the website (FR-ACC-03).
 */
class GeneralSettings extends Settings
{
    public string $org_name;

    public ?string $org_legal_name;

    public ?string $registration_no;

    public ?string $tax_exemption_ref;

    public ?string $address;

    public ?string $phone;

    public ?string $email;

    public ?string $website;

    public ?string $logo_path;

    public ?string $receipt_footer_text;

    public static function group(): string
    {
        return 'general';
    }
}
