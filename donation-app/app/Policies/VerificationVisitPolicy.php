<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Beneficiary;
use App\Models\User;
use App\Models\VerificationVisit;

/**
 * Verification visits (area: beneficiaries). Recorded only through the application's
 * "Record visit" button (ApplicationPolicy::recordVisit); never edited or deleted —
 * a correction is a new visit.
 */
class VerificationVisitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('beneficiaries.view_any');
    }

    public function view(User $user, VerificationVisit $visit): bool
    {
        return $user->can('beneficiaries.view')
            && (! Beneficiary::seesOnlyOwnCases($user) || $visit->officer_id === $user->id || $visit->application->isCaseOf($user));
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, VerificationVisit $visit): bool
    {
        return false;
    }

    public function delete(User $user, VerificationVisit $visit): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
