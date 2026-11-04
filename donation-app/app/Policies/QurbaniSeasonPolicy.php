<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\QurbaniSeason;
use App\Models\User;

/** Qurbani (area: qurbani — SA F · Manager C · Finance C · Campaign Manager C · Field Officer C · Trustee V · Auditor V). */
class QurbaniSeasonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('qurbani.view_any');
    }

    public function view(User $user, QurbaniSeason $record): bool
    {
        return $user->can('qurbani.view');
    }

    public function create(User $user): bool
    {
        return $user->can('qurbani.create');
    }

    public function update(User $user, QurbaniSeason $record): bool
    {
        return $user->can('qurbani.update');
    }

    public function delete(User $user, QurbaniSeason $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
