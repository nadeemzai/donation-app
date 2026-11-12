<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Banner;
use App\Support\ApiFormat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A home slider banner. button_url is always a full URL (site paths are expanded),
 * so the app can open it in a browser or match it to a screen.
 *
 * @mixin Banner
 */
class BannerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Banner $banner */
        $banner = $this->resource;

        return [
            'id' => $banner->id,
            'title' => $banner->title,
            'subtitle' => $banner->subtitle,
            'image_url' => $banner->imageUrl(),
            'button_label' => $banner->button_label,
            'button_url' => ApiFormat::url($banner->button_url),
            'text_align' => $banner->text_align,
        ];
    }
}
