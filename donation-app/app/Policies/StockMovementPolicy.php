<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;

/** Stock movement lines are append-only: visible to inventory staff, written only by InventoryService. */
class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('qurbani.view_any');
    }

    public function view(User $user, StockMovement $movement): bool
    {
        return $user->can('qurbani.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StockMovement $movement): bool
    {
        return false;
    }

    public function delete(User $user, StockMovement $movement): bool
    {
        return false;
    }
}
