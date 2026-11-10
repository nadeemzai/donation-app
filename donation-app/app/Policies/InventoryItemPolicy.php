<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;

/** Inventory items (area: qurbani — "qurbani & inventory"). Never deleted, only switched off. */
class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('qurbani.view_any');
    }

    public function view(User $user, InventoryItem $item): bool
    {
        return $user->can('qurbani.view');
    }

    public function create(User $user): bool
    {
        return $user->can('qurbani.create');
    }

    public function update(User $user, InventoryItem $item): bool
    {
        return $user->can('qurbani.update');
    }

    /** Count correction after a physical count. */
    public function adjust(User $user, InventoryItem $item): bool
    {
        return $user->can('qurbani.update');
    }

    public function delete(User $user, InventoryItem $item): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
