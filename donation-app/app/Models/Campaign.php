<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\CampaignFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * An appeal that collects money into one fund (FR-CMP-02, BR-01).
 *
 * - status, raised_amount, donor_count, published_at and closed_at are not
 *   mass-assignable: they change only through CampaignService (Week 2, Day 3)
 *   and donation confirmation (Week 3).
 * - slug is generated from the title and stays unique, including deleted campaigns.
 *
 * @property int $id
 * @property int $fund_id
 * @property string|null $slug
 * @property string $title
 * @property string|null $summary
 * @property string|null $story
 * @property string|null $goal_amount
 * @property string $raised_amount
 * @property int $donor_count
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property CampaignStatus $status
 * @property bool $is_featured
 * @property Carbon|null $published_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Fund $fund
 */
#[Fillable(['fund_id', 'title', 'slug', 'summary', 'story', 'goal_amount', 'starts_at', 'ends_at', 'is_featured'])]
class Campaign extends Model implements HasMedia
{
    /** @use HasFactory<CampaignFactory> */
    use Auditable, HasFactory, InteractsWithMedia, SoftDeletes;

    public const COVER = 'cover';

    public const GALLERY = 'gallery';

    protected $attributes = [
        'status' => 'draft',
        'raised_amount' => '0.00',
        'donor_count' => 0,
    ];

    protected static function booted(): void
    {
        static::saving(function (Campaign $campaign): void {
            if (blank($campaign->slug)) {
                $campaign->slug = static::uniqueSlug($campaign->title, $campaign->id);
            }

            if ($campaign->starts_at !== null && $campaign->ends_at !== null && $campaign->ends_at->lt($campaign->starts_at)) {
                throw new DomainException('A campaign cannot end before it starts.');
            }

            if ($campaign->goal_amount !== null && bccomp((string) $campaign->goal_amount, '0', 2) <= 0) {
                throw new DomainException('The goal amount must be more than zero.');
            }
        });
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'campaign';
        $base = Str::limit($base, 100, '');
        $slug = $base;
        $i = 2;

        while (static::withTrashed()->where('slug', $slug)->when($ignoreId, fn (Builder $q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * Cover: one image shown on cards and the campaign page. Gallery: up to 12 photos.
     * Stored on the public disk — these images are shown on the public website.
     */
    public function registerMediaCollections(): void
    {
        $images = ['image/jpeg', 'image/png', 'image/webp'];

        $this->addMediaCollection(self::COVER)
            ->singleFile()
            ->acceptsMimeTypes($images)
            ->useDisk('public');

        $this->addMediaCollection(self::GALLERY)
            ->acceptsMimeTypes($images)
            ->useDisk('public');
    }

    public function hasCover(): bool
    {
        return $this->hasMedia(self::COVER);
    }

    public function isEditable(): bool
    {
        return $this->status !== CampaignStatus::Closed;
    }

    /**
     * @return BelongsTo<Fund, $this>
     */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    /**
     * @return HasMany<CampaignUpdate, $this>
     */
    public function updates(): HasMany
    {
        return $this->hasMany(CampaignUpdate::class)->latest('published_at');
    }

    /**
     * @return HasMany<Donation, $this>
     */
    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    /**
     * Percentage of the goal raised (0–100), or null for open-ended causes.
     */
    public function progressPercent(): ?int
    {
        if ($this->goal_amount === null || bccomp((string) $this->goal_amount, '0', 2) <= 0) {
            return null;
        }

        $percent = (int) bcdiv(bcmul((string) $this->raised_amount, '100', 2), (string) $this->goal_amount, 0);

        return min(100, max(0, $percent));
    }

    public function hasEnded(): bool
    {
        return $this->ends_at !== null && $this->ends_at->copy()->endOfDay()->isPast();
    }

    /**
     * @param  Builder<Campaign>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', CampaignStatus::Live->value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'goal_amount' => 'decimal:2',
            'raised_amount' => 'decimal:2',
            'donor_count' => 'integer',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
