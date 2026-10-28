<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Support\PermissionMatrix;

// §3.1 review: every admin screen, every role, checked against config/permissions.php.
// If a screen and the matrix ever disagree, this test fails.

/**
 * @return array<string, array{0: string, 1: string, 2: string}> url, area, ability
 */
function adminScreens(): array
{
    return [
        'staff users list' => ['/admin/users', 'users', 'view_any'],
        'staff users create' => ['/admin/users/create', 'users', 'create'],
        'login history' => ['/admin/login-histories', 'audit_log', 'view_any'],
        'activity log' => ['/admin/activity-log', 'audit_log', 'view_any'],
        'funds list' => ['/admin/funds', 'funds', 'view_any'],
        'funds create' => ['/admin/funds/create', 'funds', 'create'],
        'campaigns list' => ['/admin/campaigns', 'campaigns', 'view_any'],
        'campaigns create' => ['/admin/campaigns/create', 'campaigns', 'create'],
        'donors list' => ['/admin/donors', 'donors', 'view_any'],
        'donors create' => ['/admin/donors/create', 'donors', 'create'],
        'donations list' => ['/admin/donations', 'donations', 'view_any'],
        'donations create' => ['/admin/donations/create', 'donations', 'create'],
        'fund ledger' => ['/admin/fund-ledger', 'ledger', 'view_any'],
    ];
}

it('matches the permission matrix on every screen for every role', function (Role $role) {
    $user = staffUser($role);
    $granted = PermissionMatrix::forRole($role->value);
    $mismatches = [];

    foreach (adminScreens() as $screen => [$url, $area, $ability]) {
        $allowed = $role === Role::SuperAdmin || in_array(PermissionMatrix::name($area, $ability), $granted, true);
        $status = $this->actingAs($user)->get($url)->getStatusCode();

        if ($status !== ($allowed ? 200 : 403)) {
            $mismatches[] = sprintf('%s (%s): expected %d, got %d', $screen, $url, $allowed ? 200 : 403, $status);
        }
    }

    expect($mismatches)->toBe([]);
})->with(Role::cases());

it('keeps settings pages as the matrix says (users area)', function (Role $role) {
    $user = staffUser($role);
    $granted = PermissionMatrix::forRole($role->value);
    $canView = $role === Role::SuperAdmin || in_array('users.view_any', $granted, true);
    $canEdit = $role === Role::SuperAdmin || in_array('users.update', $granted, true);

    $this->actingAs($user)->get('/admin/settings/general')->assertStatus($canView ? 200 : 403);
    $this->actingAs($user)->get('/admin/settings/gateways')->assertStatus($canEdit ? 200 : 403);
})->with(Role::cases());
