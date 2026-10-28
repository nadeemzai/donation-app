<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Fund;
use App\Models\User;

/**
 * Funds have their own area in the permission matrix (§3.1: Funds). Finance can create and edit them.
 */
class FundPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('funds.view_any');
    }

    public function view(User $user, Fund $fund): bool
    {
        return $user->can('funds.view');
    }

    public function create(User $user): bool
    {
        return $user->can('funds.create');
    }

    public function update(User $user, Fund $fund): bool
    {
        return $user->can('funds.update');
    }

    /**
     * Only an empty fund can be removed: no campaigns, no donations, no ledger
     * entries and a zero balance.
     */
    public function delete(User $user, Fund $fund): bool
    {
        return $user->can('funds.delete')
            && ! $fund->campaigns()->withTrashed()->exists()
            && ! $fund->donations()->exists()
            && ! $fund->ledgerEntries()->exists()
            && bccomp($fund->balance, '0', 2) === 0;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Fund $fund): bool
    {
        return $user->can('funds.delete');
    }

    public function forceDelete(User $user, Fund $fund): bool
    {
        return false;
    }
}
