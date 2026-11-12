<?php

declare(strict_types=1);

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One share of a booking: the name the Qurbani is offered on behalf of, and (Day 2) the tagged
 * animal it is allocated to. Written by QurbaniService.
 *
 * @property int $id
 * @property int $booking_id
 * @property int $position
 * @property string $name
 * @property int|null $animal_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read QurbaniBooking $booking
 * @property-read QurbaniAnimal|null $animal
 */
class QurbaniShare extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (QurbaniShare $share): void {
            $share->name = trim(preg_replace('/\s+/u', ' ', (string) $share->name) ?? '');

            if (mb_strlen($share->name) < 2) {
                throw new DomainException('Enter the name for every share.');
            }
        });
    }

    /**
     * @return BelongsTo<QurbaniBooking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(QurbaniBooking::class, 'booking_id');
    }

    /**
     * @return BelongsTo<QurbaniAnimal, $this>
     */
    public function animal(): BelongsTo
    {
        return $this->belongsTo(QurbaniAnimal::class, 'animal_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }
}
