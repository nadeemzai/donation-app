<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Banner;
use App\Models\User;

/** Website banners (area: cms). */
class BannerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cms.view_any');
    }

    public function view(User $user, Banner $banner): bool
    {
        return $user->can('cms.view');
    }

    public function create(User $user): bool
    {
        return $user->can('cms.create');
    }

    public function update(User $user, Banner $banner): bool
    {
        return $user->can('cms.update');
    }

    public function delete(User $user, Banner $banner): bool
    {
        return $user->can('cms.update');
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Banner $banner): bool
    {
        return false;
    }

    public function forceDelete(User $user, Banner $banner): bool
    {
        return false;
    }
}
