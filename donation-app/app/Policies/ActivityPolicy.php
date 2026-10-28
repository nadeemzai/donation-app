<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * The audit trail is read-only for everyone, super admin included (NFR-07).
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('audit_log.view_any');
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->can('audit_log.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Activity $activity): bool
    {
        return false;
    }

    public function delete(User $user, Activity $activity): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
