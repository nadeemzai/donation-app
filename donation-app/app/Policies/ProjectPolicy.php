<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Projects (area: projects — SA F · Manager C · Finance V · Campaign Manager C ·
 * Field Officer V · Trustee V · Auditor V). Archived, never erased.
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('projects.view_any');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->can('projects.view');
    }

    public function create(User $user): bool
    {
        return $user->can('projects.create');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->can('projects.update') && ! $project->trashed();
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can('projects.delete');
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Project $project): bool
    {
        return $user->can('projects.delete');
    }

    public function forceDelete(User $user, Project $project): bool
    {
        return false;
    }
}
