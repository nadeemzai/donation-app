<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\CampaignUpdate;
use App\Support\ApiFormat;
use App\Support\SafeHtml;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A published progress update on a campaign.
 *
 * @mixin CampaignUpdate
 */
class CampaignUpdateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CampaignUpdate $update */
        $update = $this->resource;

        return [
            'id' => $update->id,
            'title' => $update->title,
            'body_html' => SafeHtml::clean($update->body),
            'published_at' => ApiFormat::time($update->published_at),
            'photos' => $update->getMedia(CampaignUpdate::PHOTOS)->map(fn (Media $media): string => $media->getUrl())->values()->all(),
            // In the impact feed (several campaigns) the app needs to know which one.
            'campaign' => $update->relationLoaded('campaign') ? [
                'slug' => $update->campaign->slug,
                'title' => $update->campaign->title,
            ] : null,
        ];
    }
}
