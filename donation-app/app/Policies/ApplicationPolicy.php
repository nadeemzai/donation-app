<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Beneficiary;
use App\Models\User;

/**
 * Applications (area: beneficiaries). Field officers work only their own cases.
 * Deciding (approve / reject / re-open) needs beneficiaries.approve — in the matrix
 * that is the Super Admin (founder) only. Applications are never deleted.
 */
class ApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('beneficiaries.view_any');
    }

    public function view(User $user, Application $application): bool
    {
        return $user->can('beneficiaries.view') && $this->inScope($user, $application);
    }

    public function create(User $user): bool
    {
        return $user->can('beneficiaries.create');
    }

    /** Details can be corrected until verification starts. */
    public function update(User $user, Application $application): bool
    {
        return $user->can('beneficiaries.update')
            && $application->status === ApplicationStatus::Submitted
            && $this->inScope($user, $application);
    }

    public function startVerification(User $user, Application $application): bool
    {
        return $user->can('beneficiaries.update')
            && $application->status === ApplicationStatus::Submitted
            && $this->inScope($user, $application);
    }

    /** FR-BEN-03: the officer records a home visit while the application is open. */
    public function recordVisit(User $user, Application $application): bool
    {
        return $user->can('beneficiaries.update')
            && $application->status->isOpen()
            && $this->inScope($user, $application);
    }

    public function approve(User $user, Application $application): bool
    {
        return $user->can('beneficiaries.approve') && $application->status === ApplicationStatus::Verifying;
    }

    public function reject(User $user, Application $application): bool
    {
        return $user->can('beneficiaries.approve') && $application->status->isOpen();
    }

    public function reopen(User $user, Application $application): bool
    {
        return $user->can('beneficiaries.approve') && $application->status === ApplicationStatus::Rejected;
    }

    /** Choose which officer visits. Field officers cannot hand work around. */
    public function assign(User $user): bool
    {
        return $user->can('beneficiaries.update') && ! Beneficiary::seesOnlyOwnCases($user);
    }

    public function delete(User $user, Application $application): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Application $application): bool
    {
        return false;
    }

    private function inScope(User $user, Application $application): bool
    {
        return ! Beneficiary::seesOnlyOwnCases($user) || $application->isCaseOf($user);
    }
}
