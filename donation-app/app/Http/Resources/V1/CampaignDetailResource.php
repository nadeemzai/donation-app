<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Campaign;
use App\Support\SafeHtml;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One campaign with its story (cleaned HTML) and gallery.
 *
 * @mixin Campaign
 */
class CampaignDetailResource extends CampaignResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Campaign $campaign */
        $campaign = $this->resource;

        return [
            ...parent::toArray($request),
            'story_html' => SafeHtml::clean($campaign->story),
            'gallery' => $campaign->getMedia(Campaign::GALLERY)->map(fn (Media $media): string => $media->getUrl())->values()->all(),
        ];
    }
}
