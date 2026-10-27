<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates the founder's super admin account from ADMIN_* in .env.
 * Never overwrites an existing password.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array{name: string|null, email: string|null, password: string|null} $admin */
        $admin = config('donation.admin');

        if (blank($admin['email']) || blank($admin['password'])) {
            $this->command?->warn('ADMIN_EMAIL / ADMIN_PASSWORD not set in .env — super admin not created.');

            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => $admin['email']],
            ['name' => $admin['name'] ?? 'Super Admin', 'password' => $admin['password'], 'is_active' => true],
        );

        $user->assignRole(Role::SuperAdmin->value);

        $this->command?->info("Super admin ready: {$user->email}");
    }
}
