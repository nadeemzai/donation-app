<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\CampaignUpdateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A progress post on a campaign (FR-CMP-04). Unpublished (published_at null) = draft.
 *
 * @property int $id
 * @property int $campaign_id
 * @property string $title
 * @property string $body
 * @property Carbon|null $published_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['campaign_id', 'title', 'body', 'published_at', 'created_by'])]
class CampaignUpdate extends Model implements HasMedia
{
    /** @use HasFactory<CampaignUpdateFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    public const PHOTOS = 'photos';

    /** Up to 8 photos per update, shown on the public website. */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PHOTOS)
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useDisk('public');
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
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    /**
     * @param  Builder<CampaignUpdate>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }
}
