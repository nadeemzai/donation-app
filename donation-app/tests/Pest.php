<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run on a fresh in-memory SQLite database (see phpunit.xml)
| and are wrapped in a transaction by RefreshDatabase.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * An active staff user with the given role. Seeds roles/permissions first.
 * Roles that require two-factor login get an authenticator secret unless
 * $withMfa is false.
 *
 * @param  array<string, mixed>  $attributes
 */
function staffUser(Role $role, array $attributes = [], bool $withMfa = true): User
{
    (new RolesAndPermissionsSeeder)->run();

    $user = User::factory()->create($attributes);
    $user->assignRole($role->value);

    if ($withMfa && $role->requiresMfa()) {
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    }

    return $user;
}
