<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\BannerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A home page slider banner (website). Image on the public disk.
 *
 * @property int $id
 * @property string $title
 * @property string|null $subtitle
 * @property string|null $button_label
 * @property string|null $button_url
 * @property string $text_align
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'subtitle', 'button_label', 'button_url', 'text_align', 'sort_order', 'is_active', 'starts_at', 'ends_at'])]
class Banner extends Model implements HasMedia
{
    /** @use HasFactory<BannerFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    public const IMAGE = 'image';

    protected $attributes = [
        'text_align' => 'left',
        'sort_order' => 0,
        'is_active' => true,
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::IMAGE)
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->useDisk('public');
    }

    /**
     * Banners to show right now: active, inside their dates, in order.
     *
     * @param  Builder<Banner>  $query
     */
    public function scopeShowing(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function imageUrl(): ?string
    {
        $url = $this->getFirstMediaUrl(self::IMAGE);

        return $url !== '' ? $url : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
