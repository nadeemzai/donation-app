<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\ImpactUpdate;
use App\Support\ApiFormat;
use App\Support\SafeHtml;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A published impact update on a project.
 *
 * @mixin ImpactUpdate
 */
class ImpactUpdateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ImpactUpdate $update */
        $update = $this->resource;

        return [
            'id' => $update->id,
            'title' => $update->title,
            'body_html' => SafeHtml::clean($update->body),
            'published_at' => ApiFormat::time($update->published_at),
            'photos' => $update->getMedia(ImpactUpdate::PHOTOS)->map(fn (Media $media): string => $media->getUrl())->values()->all(),
            'project' => [
                'slug' => $update->project->slug,
                'name' => $update->project->name,
            ],
            'web_url' => route('projects.show', $update->project->slug).'#update-'.$update->id,
        ];
    }
}
