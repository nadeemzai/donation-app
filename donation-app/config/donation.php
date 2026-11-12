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

    // Operations (Week 12 Day 4): who gets alerts (ops:health, failed ledger checks, backups),
    // and an optional heartbeat URL (healthchecks.io, Better Stack…) pinged every 10 minutes —
    // if the pings stop, the server, cron or scheduler is down.
    'ops' => [
        'alert_email' => env('OPS_ALERT_EMAIL', env('ADMIN_EMAIL')),
        'heartbeat_url' => env('HEARTBEAT_URL'),
    ],

    // Load testing (Week 12 Day 2): requests from these IPs skip the rate limits so k6 can push
    // the donate and callback paths. Comma-separated, e.g. "203.0.113.7". IGNORED in production.
    'load_test_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('LOAD_TEST_IPS', ''))))),

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

    // API docs at /docs, /docs.postman and /docs.openapi (Week 7 Day 5). Always on outside
    // production; on the live site only when this is true.
    'api_docs_public' => (bool) env('API_DOCS_PUBLIC', false),

    // Mobile app (Week 7): the app asks the donor to update below min_version.
    'app' => [
        'min_version' => env('APP_MIN_VERSION', '1.0.0'),
        'latest_version' => env('APP_LATEST_VERSION', '1.0.0'),
        'android_url' => env('APP_ANDROID_URL'),
        'ios_url' => env('APP_IOS_URL'),
    ],

];
