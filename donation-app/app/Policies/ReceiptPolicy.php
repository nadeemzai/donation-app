<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Receipt;
use App\Models\User;

/**
 * Receipts (area: receipts). Issued by ReceiptService only; never edited or deleted.
 */
class ReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('receipts.view_any');
    }

    public function view(User $user, Receipt $receipt): bool
    {
        return $user->can('receipts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('receipts.create');
    }

    public function update(User $user, Receipt $receipt): bool
    {
        return false;
    }

    public function delete(User $user, Receipt $receipt): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Receipt $receipt): bool
    {
        return false;
    }

    public function forceDelete(User $user, Receipt $receipt): bool
    {
        return false;
    }
}
