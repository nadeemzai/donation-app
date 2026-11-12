<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\MessageStatus;
use App\Models\MessageLog;
use App\Models\User;

/**
 * Message log (area: donors — it holds donor contact details). Read-only; staff who can
 * edit donors may retry a failed message.
 */
class MessageLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('donors.view_any');
    }

    public function view(User $user, MessageLog $log): bool
    {
        return $user->can('donors.view');
    }

    public function retry(User $user, MessageLog $log): bool
    {
        return $user->can('donors.update') && $log->status === MessageStatus::Failed;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MessageLog $log): bool
    {
        return false;
    }

    public function delete(User $user, MessageLog $log): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, MessageLog $log): bool
    {
        return false;
    }

    public function forceDelete(User $user, MessageLog $log): bool
    {
        return false;
    }
}
