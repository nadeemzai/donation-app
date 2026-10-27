<?php

declare(strict_types=1);

use App\Enums\Role;

// Super admin holds every matrix permission, but policies that say "never" still apply.

it('gives super admin every permission name', function () {
    $admin = staffUser(Role::SuperAdmin);

    expect($admin->can('users.delete'))->toBeTrue()
        ->and($admin->can('disbursements.approve'))->toBeTrue();
});

it('does not let super admin bypass a policy that always refuses', function () {
    $admin = staffUser(Role::SuperAdmin);
    $other = staffUser(Role::Finance);

    expect($admin->can('delete', $other))->toBeFalse()
        ->and($admin->can('update', $other))->toBeTrue();
});
