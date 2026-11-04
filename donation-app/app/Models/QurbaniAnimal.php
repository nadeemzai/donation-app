<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QurbaniAnimalStatus;
use App\Models\Concerns\Auditable;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A tagged animal in a season (FR-QRB-03). Shares are allocated to it on Day 2; its status
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
    use Auditable;

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
        });
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
