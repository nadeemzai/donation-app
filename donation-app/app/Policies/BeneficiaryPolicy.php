<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Beneficiary;
use App\Models\User;

/**
 * Beneficiaries (area: beneficiaries). Field officers reach only their own cases
 * (assigned to them or registered by them). Archived, never erased.
 */
class BeneficiaryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('beneficiaries.view_any');
    }

    public function view(User $user, Beneficiary $beneficiary): bool
    {
        return $user->can('beneficiaries.view') && $this->inScope($user, $beneficiary);
    }

    public function create(User $user): bool
    {
        return $user->can('beneficiaries.create');
    }

    public function update(User $user, Beneficiary $beneficiary): bool
    {
        return $user->can('beneficiaries.update') && ! $beneficiary->trashed() && $this->inScope($user, $beneficiary);
    }

    /** Who looks after the case. Field officers cannot hand cases around. */
    public function assign(User $user): bool
    {
        return $user->can('beneficiaries.update') && ! Beneficiary::seesOnlyOwnCases($user);
    }

    public function delete(User $user, Beneficiary $beneficiary): bool
    {
        return $user->can('beneficiaries.delete');
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Beneficiary $beneficiary): bool
    {
        return $user->can('beneficiaries.delete');
    }

    public function forceDelete(User $user, Beneficiary $beneficiary): bool
    {
        return false;
    }

    private function inScope(User $user, Beneficiary $beneficiary): bool
    {
        return ! Beneficiary::seesOnlyOwnCases($user) || $beneficiary->isCaseOf($user);
    }
}
