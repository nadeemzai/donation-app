<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Gateway payment attempts (area: donations). Read-only for everyone — payments change
 * only through gateway callbacks and status checks (PaymentService).
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('donations.view_any');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->can('donations.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Payment $payment): bool
    {
        return false;
    }

    public function forceDelete(User $user, Payment $payment): bool
    {
        return false;
    }
}
