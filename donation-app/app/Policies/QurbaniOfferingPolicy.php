<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\QurbaniOffering;
use App\Models\User;

/** Animals and prices of a season follow the qurbani area. Never deleted — switch "Offered" off. */
class QurbaniOfferingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('qurbani.view_any');
    }

    public function view(User $user, QurbaniOffering $offering): bool
    {
        return $user->can('qurbani.view');
    }

    public function create(User $user): bool
    {
        return $user->can('qurbani.update');
    }

    public function update(User $user, QurbaniOffering $offering): bool
    {
        return $user->can('qurbani.update');
    }

    public function delete(User $user, QurbaniOffering $offering): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
