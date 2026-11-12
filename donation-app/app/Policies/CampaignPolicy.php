<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\User;

/**
 * Campaigns (area: campaigns). Closed campaigns are read-only for everyone.
 */
class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('campaigns.view_any');
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->can('campaigns.view');
    }

    public function create(User $user): bool
    {
        return $user->can('campaigns.create');
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->can('campaigns.update') && $campaign->isEditable();
    }

    public function publish(User $user, Campaign $campaign): bool
    {
        return $user->can('campaigns.update') && $campaign->status === CampaignStatus::Draft;
    }

    public function close(User $user, Campaign $campaign): bool
    {
        return $user->can('campaigns.update') && $campaign->status !== CampaignStatus::Closed;
    }

    /** Only an untouched draft can be deleted. */
    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->can('campaigns.delete')
            && $campaign->status === CampaignStatus::Draft
            && $campaign->donor_count === 0
            && ! $campaign->donations()->exists()
            && bccomp($campaign->raised_amount, '0', 2) === 0;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Campaign $campaign): bool
    {
        return $user->can('campaigns.delete');
    }

    public function forceDelete(User $user, Campaign $campaign): bool
    {
        return false;
    }
}
