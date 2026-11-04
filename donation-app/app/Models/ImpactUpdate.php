<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\ImpactUpdateFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A progress post on a project (FR-PRJ-02). published_at null = draft, future = scheduled.
 * Only a project shown on the website can publish. Donors are messaged once (notified_at).
 *
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property string $body
 * @property Carbon|null $published_at
 * @property bool $notify_donors
 * @property Carbon|null $notified_at
 * @property int $notified_count
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 * @property-read User|null $author
 */
#[Fillable(['project_id', 'title', 'body', 'published_at', 'notify_donors', 'created_by'])]
class ImpactUpdate extends Model implements HasMedia
{
    /** @use HasFactory<ImpactUpdateFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    /** Up to 8 photos, shown publicly. */
    public const PHOTOS = 'photos';

    protected $attributes = [
        'notify_donors' => true,
        'notified_count' => 0,
    ];

    protected static function booted(): void
    {
        static::saving(function (ImpactUpdate $update): void {
            $update->title = trim((string) $update->title);

            if ($update->title === '' || trim(strip_tags((string) $update->body)) === '') {
                throw new DomainException('An update needs a title and some text.');
            }

            if ($update->published_at !== null) {
                $project = Project::withTrashed()->find($update->project_id);

                if ($project === null || ! $project->is_public || $project->trashed()) {
                    throw new DomainException('Turn on "Show on the website" for the project before publishing its updates. Save this one as a draft for now.');
                }
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PHOTOS)
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useDisk('public');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && ! $this->published_at->isFuture();
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->published_at === null => 'Draft',
            $this->published_at->isFuture() => 'Scheduled',
            default => 'Published',
        };
    }

    /**
     * Published updates of projects shown on the website.
     *
     * @param  Builder<ImpactUpdate>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereHas('project', fn (Builder $p) => $p->where('is_public', true));
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'notify_donors' => 'boolean',
            'notified_at' => 'datetime',
            'notified_count' => 'integer',
        ];
    }
}
