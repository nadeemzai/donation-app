<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Donor;
use App\Models\User;

/**
 * Donor records (area: donors). Donors are archived (soft-deleted), never erased.
 */
class DonorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('donors.view_any');
    }

    public function view(User $user, Donor $donor): bool
    {
        return $user->can('donors.view');
    }

    public function create(User $user): bool
    {
        return $user->can('donors.create');
    }

    public function update(User $user, Donor $donor): bool
    {
        return $user->can('donors.update');
    }

    public function delete(User $user, Donor $donor): bool
    {
        return $user->can('donors.delete');
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Donor $donor): bool
    {
        return $user->can('donors.delete');
    }

    public function forceDelete(User $user, Donor $donor): bool
    {
        return false;
    }
}
