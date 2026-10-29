<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

/** Website pages (area: cms). About and Contact can't be deleted. */
class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cms.view_any');
    }

    public function view(User $user, Page $page): bool
    {
        return $user->can('cms.view');
    }

    public function create(User $user): bool
    {
        return $user->can('cms.create');
    }

    public function update(User $user, Page $page): bool
    {
        return $user->can('cms.update');
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->can('cms.update') && ! $page->isProtected();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Page $page): bool
    {
        return false;
    }

    public function forceDelete(User $user, Page $page): bool
    {
        return false;
    }
}
