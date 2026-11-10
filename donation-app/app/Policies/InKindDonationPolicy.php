<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InKindDonation;
use App\Models\User;

/** Goods received (area: qurbani — "qurbani & inventory"). Recorded once, never edited or deleted. */
class InKindDonationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('qurbani.view_any');
    }

    public function view(User $user, InKindDonation $donation): bool
    {
        return $user->can('qurbani.view');
    }

    public function create(User $user): bool
    {
        return $user->can('qurbani.create');
    }

    public function update(User $user, InKindDonation $donation): bool
    {
        return false;
    }

    public function delete(User $user, InKindDonation $donation): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
