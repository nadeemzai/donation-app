<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QurbaniSeasonStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\QurbaniSeasonFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A Qurbani season (FR-QRB-01): Eid date, the fund bookings are paid into, prices per animal.
 *
 * - Slaughter on the 3 days of Eid (eid_date, +1, +2).
 * - Booking closes 3 days before Eid: booking_closes_on is always eid_date − 3 days.
 * - One season open for booking at a time. Never a Zakat fund (Qurbani is not Zakat).
 *
 * @property int $id
 * @property string $name
 * @property Carbon $eid_date
 * @property Carbon $booking_closes_on
 * @property int $fund_id
 * @property QurbaniSeasonStatus $status
 * @property bool $allow_overseas
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Fund $fund
 */
#[Fillable(['name', 'eid_date', 'fund_id', 'status', 'allow_overseas', 'notes', 'created_by'])]
class QurbaniSeason extends Model
{
    /** @use HasFactory<QurbaniSeasonFactory> */
    use Auditable, HasFactory;

    /** Booking closes this many days before the first day of Eid. */
    public const CUTOFF_DAYS = 3;

    /** Qurbani is offered over the 3 days of Eid ul Adha. */
    public const SLAUGHTER_DAYS = 3;

    protected $attributes = [
        'status' => 'draft',
        'allow_overseas' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (QurbaniSeason $season): void {
            $season->name = trim((string) $season->name);

            if ($season->name === '') {
                throw new DomainException('Give the season a name, e.g. "Eid ul Adha 1448".');
            }

            if ($season->getAttribute('eid_date') === null) {
                throw new DomainException('Enter the first day of Eid ul Adha.');
            }

            $season->booking_closes_on = $season->eid_date->copy()->subDays(self::CUTOFF_DAYS);

            if ($season->isDirty('fund_id')) {
                $fund = Fund::withTrashed()->find($season->fund_id) ?? throw new DomainException('Choose the fund Qurbani payments go into.');

                if ($fund->is_zakat) {
                    throw new DomainException('Qurbani payments cannot go into a Zakat fund.');
                }
            }

            if ($season->status === QurbaniSeasonStatus::Open
                && self::query()->where('status', QurbaniSeasonStatus::Open->value)->whereKeyNot($season->id)->exists()) {
                throw new DomainException('Another season is already open for booking. Close it first.');
            }
        });
    }

    /** The season shown on the website and in the app, if any. */
    public static function current(): ?self
    {
        return self::query()->where('status', QurbaniSeasonStatus::Open->value)->with(['fund', 'offerings'])->first();
    }

    /** Open, and the cut-off (3 days before Eid) has not passed. */
    public function isTakingBookings(): bool
    {
        return $this->status === QurbaniSeasonStatus::Open && ! today()->isAfter($this->booking_closes_on);
    }

    /**
     * The three slaughter days: [1 => first day of Eid, 2 => …, 3 => …].
     *
     * @return array<int, Carbon>
     */
    public function slaughterDays(): array
    {
        $days = [];

        for ($day = 1; $day <= self::SLAUGHTER_DAYS; $day++) {
            $days[$day] = $this->eid_date->copy()->addDays($day - 1);
        }

        return $days;
    }

    /**
     * @param  Builder<QurbaniSeason>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', QurbaniSeasonStatus::Open->value);
    }

    /**
     * @return BelongsTo<Fund, $this>
     */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class)->withTrashed();
    }

    /**
     * @return HasMany<QurbaniOffering, $this>
     */
    public function offerings(): HasMany
    {
        return $this->hasMany(QurbaniOffering::class, 'season_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<QurbaniBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(QurbaniBooking::class, 'season_id');
    }

    /**
     * @return HasMany<QurbaniAnimal, $this>
     */
    public function animals(): HasMany
    {
        return $this->hasMany(QurbaniAnimal::class, 'season_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'eid_date' => 'date',
            'booking_closes_on' => 'date',
            'status' => QurbaniSeasonStatus::class,
            'allow_overseas' => 'boolean',
        ];
    }
}
