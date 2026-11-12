<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Support\ApiFormat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A campaign in lists. CampaignDetailResource adds the story.
 * Load "fund" (and "fund.media", "media" for images) to avoid extra queries.
 *
 * @mixin Campaign
 */
class CampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Campaign $campaign */
        $campaign = $this->resource;
        $cover = $campaign->getFirstMediaUrl(Campaign::COVER);

        return [
            'id' => $campaign->id,
            'slug' => $campaign->slug,
            'title' => $campaign->title,
            'summary' => $campaign->summary,
            'status' => ApiFormat::enum($campaign->status),
            'is_featured' => $campaign->is_featured,
            'is_open' => $campaign->status === CampaignStatus::Live && ! $campaign->hasEnded(),
            'goal' => ApiFormat::money($campaign->goal_amount),
            'raised' => ApiFormat::money((string) $campaign->raised_amount),
            'progress_percent' => $campaign->progressPercent(),
            'donor_count' => $campaign->donor_count,
            'starts_on' => ApiFormat::date($campaign->starts_at),
            'ends_on' => ApiFormat::date($campaign->ends_at),
            'image_url' => $cover !== '' ? $cover : $campaign->fund->imageUrl(),
            'fund' => [
                'id' => $campaign->fund->id,
                'code' => $campaign->fund->code,
                'name' => $campaign->fund->name,
                'is_zakat' => $campaign->fund->is_zakat,
            ],
            'web_url' => $campaign->slug !== null ? route('campaigns.show', $campaign->slug) : null,
        ];
    }
}
