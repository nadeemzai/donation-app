<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QurbaniAnimalStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\QurbaniAnimalFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A tagged animal in a season (FR-QRB-03). Created by staff; QurbaniAllocationService puts
 * shares on it and moves it along the statuses. Shares are allocated to it on Day 2; its status
 * moves available → allocated → slaughtered → distributed.
 *
 * @property int $id
 * @property int $season_id
 * @property int $offering_id
 * @property string $tag_no
 * @property QurbaniAnimalStatus $status
 * @property int|null $slaughter_day
 * @property string|null $location
 * @property Carbon|null $slaughtered_at
 * @property Carbon|null $distributed_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read QurbaniSeason $season
 * @property-read QurbaniOffering $offering
 */
#[Fillable(['season_id', 'offering_id', 'tag_no', 'slaughter_day', 'location', 'notes'])]
class QurbaniAnimal extends Model
{
    /** @use HasFactory<QurbaniAnimalFactory> */
    use Auditable, HasFactory;

    protected $attributes = [
        'status' => 'available',
    ];

    protected static function booted(): void
    {
        static::saving(function (QurbaniAnimal $animal): void {
            $animal->tag_no = strtoupper(trim((string) $animal->tag_no));

            if ($animal->tag_no === '') {
                throw new DomainException('Enter the animal’s tag number.');
            }

            if ($animal->slaughter_day !== null && ($animal->slaughter_day < 1 || $animal->slaughter_day > QurbaniSeason::SLAUGHTER_DAYS)) {
                throw new DomainException('Qurbani is on day 1, 2 or 3 of Eid.');
            }

            $offering = QurbaniOffering::query()->find($animal->offering_id) ?? throw new DomainException('Choose the animal type.');

            if ($animal->getAttribute('season_id') === null) {
                $animal->season_id = $offering->season_id;
            }

            if ($offering->season_id !== $animal->season_id) {
                throw new DomainException('The animal type must be from the same season.');
            }

            if (self::query()->where('season_id', $animal->season_id)->where('tag_no', $animal->tag_no)->whereKeyNot($animal->id)->exists()) {
                throw new DomainException("Tag {$animal->tag_no} is already used this season.");
            }
        });

        static::updating(function (QurbaniAnimal $animal): void {
            if ($animal->isDirty(['season_id', 'offering_id']) && $animal->shares()->exists()) {
                throw new DomainException('Move its shares to another animal before changing the animal type.');
            }
        });

        static::deleting(function (QurbaniAnimal $animal): void {
            if ($animal->shares()->exists() || $animal->status !== QurbaniAnimalStatus::Available) {
                throw new DomainException('Only an animal with no shares on it can be removed.');
            }
        });
    }

    public function sharesUsed(): int
    {
        return $this->exists ? $this->shares()->count() : 0;
    }

    /** Places still free on this animal (a cow has 7, a goat 1). */
    public function freeShares(): int
    {
        return max(0, $this->offering->shares_per_animal - $this->sharesUsed());
    }

    /** Shares can still be put on it (not yet slaughtered). */
    public function isOpen(): bool
    {
        return in_array($this->status, [QurbaniAnimalStatus::Available, QurbaniAnimalStatus::Allocated], true);
    }

    /** "C-12 · Cow · Day 1 · 3 of 7" */
    public function label(): string
    {
        return implode(' · ', array_filter([
            $this->tag_no,
            $this->offering->animal->label(),
            $this->slaughter_day !== null ? 'Day '.$this->slaughter_day : null,
            $this->sharesUsed().' of '.$this->offering->shares_per_animal,
        ]));
    }

    /**
     * @return BelongsTo<QurbaniSeason, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(QurbaniSeason::class, 'season_id');
    }

    /**
     * @return BelongsTo<QurbaniOffering, $this>
     */
    public function offering(): BelongsTo
    {
        return $this->belongsTo(QurbaniOffering::class, 'offering_id');
    }

    /**
     * @return HasMany<QurbaniShare, $this>
     */
    public function shares(): HasMany
    {
        return $this->hasMany(QurbaniShare::class, 'animal_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QurbaniAnimalStatus::class,
            'slaughter_day' => 'integer',
            'slaughtered_at' => 'datetime',
            'distributed_at' => 'datetime',
        ];
    }
}
