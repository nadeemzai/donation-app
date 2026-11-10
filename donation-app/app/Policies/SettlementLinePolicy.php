<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SettlementLine;
use App\Models\User;

/** Settlement rows are written only by SettlementService; resolving goes through the settlement. */
class SettlementLinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ledger.view_any');
    }

    public function view(User $user, SettlementLine $line): bool
    {
        return $user->can('ledger.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SettlementLine $line): bool
    {
        return false;
    }

    public function delete(User $user, SettlementLine $line): bool
    {
        return false;
    }
}
