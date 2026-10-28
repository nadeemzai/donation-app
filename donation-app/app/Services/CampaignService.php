<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Campaign status changes (FR-CMP-02, FR-CMP-05, BR-09). Status, published_at and
 * closed_at are never mass-assignable — this is the only place they change.
 */
class CampaignService
{
    /**
     * Draft → Live. A campaign goes public only when it is complete enough to show donors.
     *
     * @throws DomainException with a message staff can act on
     */
    public function publish(Campaign $campaign): Campaign
    {
        return DB::transaction(function () use ($campaign): Campaign {
            $campaign = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);

            $this->assertTransition($campaign, CampaignStatus::Live);

            $problems = $this->publishProblems($campaign);
            if ($problems !== []) {
                throw new DomainException('This campaign is not ready to publish: '.implode(' ', $problems));
            }

            $campaign->status = CampaignStatus::Live;
            $campaign->published_at ??= now();
            $campaign->save();

            activity('audit')->performedOn($campaign)->event('published')->log('Campaign published');

            return $campaign;
        });
    }

    /**
     * Draft/Live → Closed. Final: a closed campaign can't be reopened or edited.
     * Later donations go to the campaign's fund (BR-09, Week 3).
     */
    public function close(Campaign $campaign, ?string $reason = null): Campaign
    {
        return DB::transaction(function () use ($campaign, $reason): Campaign {
            $campaign = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);

            $this->assertTransition($campaign, CampaignStatus::Closed);

            $campaign->status = CampaignStatus::Closed;
            $campaign->closed_at = now();
            $campaign->save();

            activity('audit')
                ->performedOn($campaign)
                ->event('closed')
                ->withProperty('reason', $reason)
                ->log($reason ? "Campaign closed: {$reason}" : 'Campaign closed');

            return $campaign;
        });
    }

    /**
     * What's missing before a campaign can go live. Empty list = ready.
     *
     * @return list<string>
     */
    public function publishProblems(Campaign $campaign): array
    {
        $problems = [];

        if (! $campaign->fund->is_active) {
            $problems[] = 'Its fund is inactive.';
        }

        if (blank($campaign->summary)) {
            $problems[] = 'Add a short summary.';
        }

        if (! $campaign->hasCover()) {
            $problems[] = 'Add a cover image.';
        }

        if ($campaign->ends_at !== null && $campaign->ends_at->lt(CarbonImmutable::today())) {
            $problems[] = 'The end date has already passed.';
        }

        return $problems;
    }

    private function assertTransition(Campaign $campaign, CampaignStatus $next): void
    {
        if (! $campaign->status->canTransitionTo($next)) {
            throw new DomainException("A {$campaign->status->label()} campaign cannot become {$next->label()}.");
        }
    }
}
