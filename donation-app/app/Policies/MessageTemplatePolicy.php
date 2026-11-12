<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MessageTemplate;
use App\Models\User;

/**
 * Message wording (area: cms). Fixed set of templates: edit only, never create or delete.
 */
class MessageTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cms.view_any');
    }

    public function view(User $user, MessageTemplate $template): bool
    {
        return $user->can('cms.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MessageTemplate $template): bool
    {
        return $user->can('cms.update');
    }

    public function delete(User $user, MessageTemplate $template): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, MessageTemplate $template): bool
    {
        return false;
    }

    public function forceDelete(User $user, MessageTemplate $template): bool
    {
        return false;
    }
}
