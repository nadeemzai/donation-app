<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\QurbaniAnimalStatus;
use App\Models\QurbaniAnimal;
use App\Models\User;

/** Qurbani animals (area: qurbani). Staff with create/update run allocation and mark progress. */
class QurbaniAnimalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('qurbani.view_any');
    }

    public function view(User $user, QurbaniAnimal $animal): bool
    {
        return $user->can('qurbani.view');
    }

    public function create(User $user): bool
    {
        return $user->can('qurbani.create');
    }

    public function update(User $user, QurbaniAnimal $animal): bool
    {
        return $user->can('qurbani.update') && $animal->isOpen();
    }

    /** Allocate shares, move a share, mark slaughtered / distributed. */
    public function operate(User $user): bool
    {
        return $user->can('qurbani.update');
    }

    public function delete(User $user, QurbaniAnimal $animal): bool
    {
        return $user->can('qurbani.update') && $animal->status === QurbaniAnimalStatus::Available && $animal->sharesUsed() === 0;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
