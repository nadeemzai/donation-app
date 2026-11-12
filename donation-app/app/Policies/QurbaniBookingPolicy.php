<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\QurbaniBookingStatus;
use App\Models\QurbaniBooking;
use App\Models\User;

/** Qurbani (area: qurbani — SA F · Manager C · Finance C · Campaign Manager C · Field Officer C · Trustee V · Auditor V). */
class QurbaniBookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('qurbani.view_any');
    }

    public function view(User $user, QurbaniBooking $record): bool
    {
        return $user->can('qurbani.view');
    }

    /** Bookings are made by donors (website / app); staff book walk-ins on Day 2. */
    public function create(User $user): bool
    {
        return false;
    }

    /** A booking is never edited — cancel and book again. */
    public function update(User $user, QurbaniBooking $record): bool
    {
        return false;
    }

    public function cancel(User $user, QurbaniBooking $record): bool
    {
        return $user->can('qurbani.update') && $record->status === QurbaniBookingStatus::Pending;
    }

    public function delete(User $user, QurbaniBooking $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
