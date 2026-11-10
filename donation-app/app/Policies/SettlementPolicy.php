<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Settlement;
use App\Models\User;

/** Settlement files (area: ledger — "ledger & reconciliation"). Finance imports and resolves; never deleted. */
class SettlementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ledger.view_any');
    }

    public function view(User $user, Settlement $settlement): bool
    {
        return $user->can('ledger.view');
    }

    /** Import a file. */
    public function create(User $user): bool
    {
        return $user->can('ledger.create');
    }

    /** Match again and resolve flagged rows. */
    public function update(User $user, Settlement $settlement): bool
    {
        return $user->can('ledger.update');
    }

    public function delete(User $user, Settlement $settlement): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
