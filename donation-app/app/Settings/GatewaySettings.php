<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Payment methods (FR-PAY-04). Credentials are stored encrypted.
 * Card payments are Phase 2 — no card gateway here.
 */
class GatewaySettings extends Settings
{
    // JazzCash
    public bool $jazzcash_enabled;

    public string $jazzcash_mode;

    public int $jazzcash_display_order;

    public ?string $jazzcash_merchant_id;

    public ?string $jazzcash_password;

    public ?string $jazzcash_integrity_salt;

    // Easypaisa
    public bool $easypaisa_enabled;

    public string $easypaisa_mode;

    public int $easypaisa_display_order;

    public ?string $easypaisa_store_id;

    public ?string $easypaisa_hash_key;

    // Bank transfer / Raast (manual, with proof upload)
    public bool $bank_enabled;

    public int $bank_display_order;

    public ?string $bank_name;

    public ?string $bank_account_title;

    public ?string $bank_iban;

    public ?string $bank_raast_id;

    public static function group(): string
    {
        return 'gateways';
    }

    /**
     * @return list<string>
     */
    public static function encrypted(): array
    {
        return ['jazzcash_password', 'jazzcash_integrity_salt', 'easypaisa_hash_key'];
    }
}
