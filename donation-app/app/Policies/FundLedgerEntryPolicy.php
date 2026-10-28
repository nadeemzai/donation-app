<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FundLedgerEntry;
use App\Models\User;

/**
 * Fund ledger (area: ledger). Read-only for everyone: lines are written only by
 * FundLedgerService and can never be changed (CLAUDE.md rule 3).
 */
class FundLedgerEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ledger.view_any');
    }

    public function view(User $user, FundLedgerEntry $entry): bool
    {
        return $user->can('ledger.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, FundLedgerEntry $entry): bool
    {
        return false;
    }

    public function delete(User $user, FundLedgerEntry $entry): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, FundLedgerEntry $entry): bool
    {
        return false;
    }

    public function forceDelete(User $user, FundLedgerEntry $entry): bool
    {
        return false;
    }
}
