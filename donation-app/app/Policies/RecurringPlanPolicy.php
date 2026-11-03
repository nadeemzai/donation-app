<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PledgeStatus;
use App\Models\RecurringPlan;
use App\Models\User;

/** Pledges (area: donations — Finance manages them, others view). Never deleted. */
class RecurringPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('donations.view_any');
    }

    public function view(User $user, RecurringPlan $plan): bool
    {
        return $user->can('donations.view');
    }

    public function create(User $user): bool
    {
        return $user->can('donations.create');
    }

    public function update(User $user, RecurringPlan $plan): bool
    {
        return $user->can('donations.update') && ! $plan->status->isFinal();
    }

    public function pause(User $user, RecurringPlan $plan): bool
    {
        return $user->can('donations.update') && $plan->status === PledgeStatus::Active;
    }

    public function resume(User $user, RecurringPlan $plan): bool
    {
        return $user->can('donations.update') && $plan->status === PledgeStatus::Paused;
    }

    public function cancel(User $user, RecurringPlan $plan): bool
    {
        return $user->can('donations.update') && ! $plan->status->isFinal();
    }

    public function delete(User $user, RecurringPlan $plan): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, RecurringPlan $plan): bool
    {
        return false;
    }

    public function forceDelete(User $user, RecurringPlan $plan): bool
    {
        return false;
    }
}
