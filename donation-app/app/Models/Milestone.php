<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MilestoneStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\MilestoneFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A step of a project (FR-PRJ-01). completed_at is set when it is marked done.
 *
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property string|null $description
 * @property Carbon|null $due_on
 * @property MilestoneStatus $status
 * @property Carbon|null $completed_at
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 */
#[Fillable(['project_id', 'title', 'description', 'due_on', 'status', 'sort_order'])]
class Milestone extends Model
{
    /** @use HasFactory<MilestoneFactory> */
    use Auditable, HasFactory;

    protected $attributes = [
        'status' => 'pending',
        'sort_order' => 0,
    ];

    protected static function booted(): void
    {
        static::saving(function (Milestone $milestone): void {
            $milestone->title = trim((string) $milestone->title);

            if ($milestone->title === '') {
                throw new DomainException('Give the milestone a title.');
            }

            if ($milestone->isDirty('status')) {
                $milestone->completed_at = $milestone->status === MilestoneStatus::Done ? ($milestone->completed_at ?? now()) : null;
            }
        });
    }

    public function isOverdue(): bool
    {
        return $this->status !== MilestoneStatus::Done && $this->due_on !== null && $this->due_on->isBefore(today());
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'status' => MilestoneStatus::class,
            'completed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }
}
