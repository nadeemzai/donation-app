<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\DonationStatus;
use App\Models\Donation;
use App\Models\User;

/**
 * Donations (area: donations; confirming uses proof_verification — BR-03).
 *
 * There is no edit screen: a donation changes only through Confirm, Cancel and Refund,
 * and it is never deleted (BR-05).
 */
class DonationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('donations.view_any');
    }

    public function view(User $user, Donation $donation): bool
    {
        return $user->can('donations.view');
    }

    /** Record an offline (cash, cheque, bank) donation. */
    public function create(User $user): bool
    {
        return $user->can('donations.create');
    }

    public function update(User $user, Donation $donation): bool
    {
        return false;
    }

    /** BR-03: money is counted only when Finance approves the proof. */
    public function confirm(User $user, Donation $donation): bool
    {
        return $user->can('proof_verification.approve') && $donation->status === DonationStatus::Pending;
    }

    public function cancel(User $user, Donation $donation): bool
    {
        return $user->can('donations.update') && $donation->status === DonationStatus::Pending;
    }

    public function refund(User $user, Donation $donation): bool
    {
        return $user->can('donations.update')
            && in_array($donation->status, [DonationStatus::Paid, DonationStatus::PartiallyRefunded], true);
    }

    public function export(User $user): bool
    {
        return $user->can('donations.export');
    }

    public function delete(User $user, Donation $donation): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Donation $donation): bool
    {
        return false;
    }

    public function forceDelete(User $user, Donation $donation): bool
    {
        return false;
    }
}
