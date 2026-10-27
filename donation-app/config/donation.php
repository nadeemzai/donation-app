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

];
