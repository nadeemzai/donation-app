<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Milestone;
use App\Models\User;

/** Milestones follow their project: whoever may edit the project manages its milestones. */
class MilestonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('projects.view_any');
    }

    public function view(User $user, Milestone $milestone): bool
    {
        return $user->can('projects.view');
    }

    public function create(User $user): bool
    {
        return $user->can('projects.update');
    }

    public function update(User $user, Milestone $milestone): bool
    {
        return $user->can('projects.update') && ! $milestone->project->trashed();
    }

    public function delete(User $user, Milestone $milestone): bool
    {
        return $user->can('projects.update') && ! $milestone->project->trashed();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
