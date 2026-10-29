<?php

declare(strict_types=1);

use App\Settings\ApprovalSettings;
use App\Settings\GatewaySettings;
use App\Settings\GeneralSettings;
use App\Settings\NotificationSettings;
use App\Settings\NumberingSettings;
use Illuminate\Support\Facades\DB;

// FR-ACC-03 — settings exist with safe defaults

it('loads defaults for every settings group', function () {
    expect(app(GeneralSettings::class)->org_name)->toBe('Imdaad')
        ->and(app(NumberingSettings::class))
        ->donation_prefix->toBe('DN')
        ->receipt_prefix->toBe('RC')
        ->disbursement_prefix->toBe('DS')
        ->donor_prefix->toBe('DR')
        ->pad_length->toBe(6)
        ->and(app(ApprovalSettings::class)->allow_founder_self_approval)->toBeTrue() // BR-06 at launch
        ->and(app(GatewaySettings::class))
        ->jazzcash_enabled->toBeFalse()
        ->easypaisa_enabled->toBeFalse()
        ->jazzcash_mode->toBe('sandbox')
        ->and(app(NotificationSettings::class))
        ->email_enabled->toBeTrue()
        ->whatsapp_enabled->toBeFalse()
        ->sms_enabled->toBeFalse();
});

it('stores gateway and Green API secrets encrypted', function () {
    $gateways = app(GatewaySettings::class);
    $gateways->jazzcash_password = 'jc-secret-pass';
    $gateways->easypaisa_hash_key = 'ep-hash-key';
    $gateways->save();

    $notifications = app(NotificationSettings::class);
    $notifications->green_api_token_instance = 'green-token-123';
    $notifications->save();

    $raw = DB::table('settings')->pluck('payload')->implode(' ');

    expect($raw)->not->toContain('jc-secret-pass')
        ->not->toContain('ep-hash-key')
        ->not->toContain('green-token-123')
        ->and(app(GatewaySettings::class)->jazzcash_password)->toBe('jc-secret-pass')
        ->and(app(NotificationSettings::class)->green_api_token_instance)->toBe('green-token-123');
});
