<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ImpactUpdate;
use App\Models\User;

/**
 * Project impact updates follow the project (area: projects). Once donors have been
 * messaged an update stays — it can be corrected, not deleted.
 */
class ImpactUpdatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('projects.view_any');
    }

    public function view(User $user, ImpactUpdate $update): bool
    {
        return $user->can('projects.view');
    }

    public function create(User $user): bool
    {
        return $user->can('projects.update');
    }

    public function update(User $user, ImpactUpdate $update): bool
    {
        return $user->can('projects.update');
    }

    public function delete(User $user, ImpactUpdate $update): bool
    {
        return $user->can('projects.update') && $update->notified_at === null;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
