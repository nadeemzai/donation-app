<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\PermissionMatrix;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Builds roles and permissions from config/permissions.php.
 * Safe to re-run: it creates what is missing and re-syncs each role.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionMatrix::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (PermissionMatrix::roles() as $roleName) {
            Role::findOrCreate($roleName, 'web')
                ->syncPermissions(PermissionMatrix::forRole($roleName));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
