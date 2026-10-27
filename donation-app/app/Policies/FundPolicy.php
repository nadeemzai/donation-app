<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Fund;
use App\Models\User;

/**
 * Funds belong to the "campaigns" area of the permission matrix (§3.1: Funds & campaigns).
 */
class FundPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('campaigns.view_any');
    }

    public function view(User $user, Fund $fund): bool
    {
        return $user->can('campaigns.view');
    }

    public function create(User $user): bool
    {
        return $user->can('campaigns.create');
    }

    public function update(User $user, Fund $fund): bool
    {
        return $user->can('campaigns.update');
    }

    /**
     * Only an empty fund can be removed: no campaigns and a zero balance.
     * (From Week 3 also: no donations.)
     */
    public function delete(User $user, Fund $fund): bool
    {
        return $user->can('campaigns.delete')
            && ! $fund->campaigns()->withTrashed()->exists()
            && bccomp($fund->balance, '0', 2) === 0;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Fund $fund): bool
    {
        return $user->can('campaigns.delete');
    }

    public function forceDelete(User $user, Fund $fund): bool
    {
        return false;
    }
}
