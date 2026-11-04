<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Milestone;
use App\Models\Project;
use App\Support\ApiFormat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A public project (FR-PRJ-02). Budget and the share used — never payees or payouts.
 * With `milestones` loaded (the detail endpoint) it also lists the milestones.
 *
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Project $project */
        $project = $this->resource;

        return [
            'id' => $project->id,
            'code' => $project->code,
            'slug' => $project->slug,
            'name' => $project->name,
            'summary' => $project->summary,
            'status' => ApiFormat::enum($project->status),
            'location' => $project->location,
            'budget' => ApiFormat::money((string) $project->budget),
            'used_percent' => $project->spentPercent(),
            'milestones_done' => $project->milestoneProgress(),
            'starts_on' => ApiFormat::date($project->starts_on),
            'ends_on' => ApiFormat::date($project->ends_on),
            'fund' => ['id' => $project->fund->id, 'code' => $project->fund->code, 'name' => $project->fund->name],
            'web_url' => route('projects.show', $project->slug),
            'description' => $this->when($project->relationLoaded('milestones'), $project->description),
            'milestones' => $this->when($project->relationLoaded('milestones'), fn (): array => $project->milestones
                ->map(fn (Milestone $m): array => [
                    'title' => $m->title,
                    'status' => ApiFormat::enum($m->status),
                    'due_on' => ApiFormat::date($m->due_on),
                    'completed_at' => ApiFormat::time($m->completed_at),
                ])->values()->all()),
        ];
    }
}
