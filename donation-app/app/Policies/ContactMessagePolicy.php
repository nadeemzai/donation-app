<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;

/** Website contact messages (area: cms). Read and mark handled; never created or deleted by staff. */
class ContactMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cms.view_any');
    }

    public function view(User $user, ContactMessage $message): bool
    {
        return $user->can('cms.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ContactMessage $message): bool
    {
        return $user->can('cms.update');
    }

    public function delete(User $user, ContactMessage $message): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
