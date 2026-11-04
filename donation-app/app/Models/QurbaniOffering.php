<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QurbaniAnimal;
use App\Enums\QurbaniBookingStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\QurbaniOfferingFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One animal type in a season with its price per share (FR-QRB-01). shares_per_animal comes
 * from the animal (cow / camel 7, goat / sheep 1). max_shares limits how many can be booked.
 *
 * @property int $id
 * @property int $season_id
 * @property QurbaniAnimal $animal
 * @property int $shares_per_animal
 * @property string $price_per_share
 * @property int|null $max_shares
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read QurbaniSeason $season
 */
#[Fillable(['season_id', 'animal', 'price_per_share', 'max_shares', 'is_active', 'sort_order'])]
class QurbaniOffering extends Model
{
    /** @use HasFactory<QurbaniOfferingFactory> */
    use Auditable, HasFactory;

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected static function booted(): void
    {
        static::saving(function (QurbaniOffering $offering): void {
            $offering->shares_per_animal = $offering->animal->sharesPerAnimal();

            $price = trim((string) ($offering->getAttributes()['price_per_share'] ?? ''));

            if (preg_match('/^\d{1,10}(\.\d{1,2})?$/', $price) !== 1 || bccomp($price, '0', 2) <= 0) {
                throw new DomainException('Enter the price per share in rupees, more than zero.');
            }

            if ($offering->max_shares !== null && $offering->max_shares < $offering->heldShares()) {
                throw new DomainException("The limit cannot be below the {$offering->heldShares()} shares already booked.");
            }
        });

        static::updating(function (QurbaniOffering $offering): void {
            if ($offering->isDirty(['animal', 'price_per_share']) && $offering->bookings()->exists()) {
                throw new DomainException('Price and animal cannot change once shares are booked — bookings keep the price they were made at.');
            }
        });
    }

    /** Shares confirmed, or held for a donor who is paying now. */
    public function heldShares(): int
    {
        if (! $this->exists) {
            return 0;
        }

        return (int) $this->bookings()->holding()->sum('shares');
    }

    /** Shares still bookable, or null when there is no limit. */
    public function sharesLeft(): ?int
    {
        return $this->max_shares === null ? null : max(0, $this->max_shares - $this->heldShares());
    }

    public function label(): string
    {
        return $this->animal->shareLabel();
    }

    /**
     * @param  Builder<QurbaniOffering>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return BelongsTo<QurbaniSeason, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(QurbaniSeason::class, 'season_id');
    }

    /**
     * @return HasMany<QurbaniBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(QurbaniBooking::class, 'offering_id')->where('status', '!=', QurbaniBookingStatus::Cancelled->value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'animal' => QurbaniAnimal::class,
            'shares_per_animal' => 'integer',
            'price_per_share' => 'decimal:2',
            'max_shares' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
