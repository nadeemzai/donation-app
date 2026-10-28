<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Staff accounts (area: users). Staff are deactivated, never deleted,
 * so the audit trail keeps who did what.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view_any');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('users.update');
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
