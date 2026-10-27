<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LoginHistory;
use App\Models\User;

/**
 * Login history is part of the audit trail (area: audit_log). Read-only for everyone.
 */
class LoginHistoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('audit_log.view_any');
    }

    public function view(User $user, LoginHistory $loginHistory): bool
    {
        return $user->can('audit_log.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, LoginHistory $loginHistory): bool
    {
        return false;
    }

    public function delete(User $user, LoginHistory $loginHistory): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
