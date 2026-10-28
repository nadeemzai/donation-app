<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CampaignUpdate;
use App\Models\User;

/**
 * Progress posts follow campaign editing rights (area: campaigns). Posting is
 * allowed on closed campaigns too — "we delivered the blankets" comes after closing.
 */
class CampaignUpdatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('campaigns.view_any');
    }

    public function view(User $user, CampaignUpdate $update): bool
    {
        return $user->can('campaigns.view');
    }

    public function create(User $user): bool
    {
        return $user->can('campaigns.update');
    }

    public function update(User $user, CampaignUpdate $update): bool
    {
        return $user->can('campaigns.update');
    }

    public function delete(User $user, CampaignUpdate $update): bool
    {
        return $user->can('campaigns.update');
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
