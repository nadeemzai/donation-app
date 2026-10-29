<?php

declare(strict_types=1);

return [

    // Seeded super admin (DatabaseSeeder → SuperAdminSeeder). Set in .env.
    'admin' => [
        'name' => env('ADMIN_NAME', 'Super Admin'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    // Two-factor login for Super Admin, Finance and Trustee (FR-ACC-01).
    // Always enforced outside the local environment, whatever .env says.
    'mfa' => [
        'enforce' => env('APP_ENV') !== 'local' || (bool) env('MFA_ENFORCE', true),
    ],

    // First-run defaults for NotificationSettings (database/settings). After
    // that, values are managed in Admin → Settings → Notifications.
    'green_api' => [
        'url' => env('GREEN_API_URL', 'https://api.green-api.com'),
        'id_instance' => env('GREEN_API_ID_INSTANCE'),
        'token_instance' => env('GREEN_API_TOKEN_INSTANCE'),
        'rate_per_minute' => (int) env('WHATSAPP_RATE_PER_MINUTE', 20),
    ],

    // Public donate form (Week 6 Day 2). Whole rupees.
    'donate' => [
        'presets' => [1000, 2500, 5000, 10000, 25000],
        'min' => (int) env('DONATE_MIN_AMOUNT', 100),
        'max' => (int) env('DONATE_MAX_AMOUNT', 5000000),
        // Bank slip / screenshot upload limit, in kilobytes.
        'proof_max_kb' => 5120,
    ],

];
