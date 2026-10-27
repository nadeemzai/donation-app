<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use App\Support\PermissionMatrix;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as RoleModel;

// docs/srs.md §3.1 — permission matrix

it('defines a level for all 7 roles in every area', function () {
    foreach (PermissionMatrix::matrix() as $area => $row) {
        expect($row)->toHaveCount(7, "Area [{$area}] must list 7 roles");
    }

    expect(PermissionMatrix::roles())->toBe(array_map(fn (Role $r) => $r->value, Role::cases()));
});

it('seeds all 7 roles and every permission, and can be re-run safely', function () {
    (new RolesAndPermissionsSeeder)->run();
    (new RolesAndPermissionsSeeder)->run();

    expect(RoleModel::count())->toBe(7)
        ->and(Permission::count())->toBe(count(PermissionMatrix::all()));
});

it('gives each role exactly what the matrix says', function (Role $role, array $can, array $cannot) {
    $user = staffUser($role);

    foreach ($can as $permission) {
        expect($user->can($permission))->toBeTrue("{$role->value} should have {$permission}");
    }
    foreach ($cannot as $permission) {
        expect($user->can($permission))->toBeFalse("{$role->value} should not have {$permission}");
    }
})->with([
    'manager' => [Role::Manager, ['donors.create', 'disbursements.approve', 'users.view_any'], ['users.update', 'proof_verification.view', 'disbursements.create']],
    'finance' => [Role::Finance, ['proof_verification.approve', 'donations.create', 'ledger.update'], ['users.view_any', 'cms.view', 'disbursements.approve']],
    'campaign manager' => [Role::CampaignManager, ['campaigns.create', 'cms.update'], ['donations.create', 'beneficiaries.view']],
    'field officer' => [Role::FieldOfficer, ['beneficiaries.create', 'disbursements.create'], ['donors.view', 'disbursements.approve']],
    'trustee' => [Role::Trustee, ['disbursements.approve', 'ledger.view', 'audit_log.view'], ['donors.create', 'users.view_any']],
    'auditor' => [Role::Auditor, ['ledger.view', 'audit_log.view', 'users.view_any'], ['donors.create', 'disbursements.approve', 'users.update']],
]);

it('lets super admin pass every check, including ones not in the matrix', function () {
    $user = staffUser(Role::SuperAdmin);

    expect($user->can('disbursements.approve'))->toBeTrue()
        ->and($user->can('anything.else'))->toBeTrue();
});

it('creates the super admin from config and never overwrites the password', function () {
    config()->set('donation.admin', ['name' => 'Nadeem Ahmad', 'email' => 'founder@example.org', 'password' => 'first-password-123']);

    (new RolesAndPermissionsSeeder)->run();
    (new SuperAdminSeeder)->run();

    config()->set('donation.admin.password', 'changed-password-456');
    (new SuperAdminSeeder)->run();

    $admin = User::where('email', 'founder@example.org')->sole();

    expect($admin->hasRole(Role::SuperAdmin->value))->toBeTrue()
        ->and(Hash::check('first-password-123', $admin->password))->toBeTrue();
});

it('skips the super admin when ADMIN_EMAIL is not set', function () {
    config()->set('donation.admin', ['name' => null, 'email' => null, 'password' => null]);

    (new RolesAndPermissionsSeeder)->run();
    (new SuperAdminSeeder)->run();

    expect(User::count())->toBe(0);
});
