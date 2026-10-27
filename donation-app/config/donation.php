<?php

declare(strict_types=1);

return [

    // Seeded super admin (DatabaseSeeder → SuperAdminSeeder). Set in .env.
    'admin' => [
        'name' => env('ADMIN_NAME', 'Super Admin'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
