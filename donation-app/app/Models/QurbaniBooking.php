<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\QurbaniBookingStatus;
use App\Enums\SequenceKey;
use App\Models\Concerns\Auditable;
use App\Services\NumberSequenceService;
use Database\Factories\QurbaniBookingFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A donor's Qurbani booking (FR-QRB-02): a number of shares of one animal type, each with the
 * name it is offered on behalf of. Paid through the normal donation flow.
 *
 * - booking_no (QB-…) from the number sequence (BR-04).
 * - The price is fixed when booked; total = shares × price.
 * - status, hold and confirmation change only through QurbaniService.
 *
 * @property int $id
 * @property string $booking_no
 * @property int $season_id
 * @property int $offering_id
 * @property int $donor_id
 * @property int $shares
 * @property string $price_per_share
 * @property string $total_amount
 * @property QurbaniBookingStatus $status
 * @property DonationChannel $channel
 * @property Carbon|null $hold_until
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read QurbaniSeason $season
 * @property-read QurbaniOffering $offering
 * @property-read Donor $donor
 * @property-read Donation|null $donation
 */
#[Fillable(['season_id', 'offering_id', 'donor_id', 'shares', 'price_per_share', 'channel', 'hold_until', 'created_by'])]
class QurbaniBooking extends Model
{
    /** @use HasFactory<QurbaniBookingFactory> */
    use Auditable, HasFactory;

    protected $attributes = [
        'status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::creating(function (QurbaniBooking $booking): void {
            if (blank($booking->getAttribute('booking_no'))) {
                $booking->booking_no = app(NumberSequenceService::class)->next(SequenceKey::QurbaniBooking);
            }
        });

        static::saving(function (QurbaniBooking $booking): void {
            if ($booking->shares < 1) {
                throw new DomainException('Book at least one share.');
            }

            $booking->total_amount = bcmul((string) $booking->price_per_share, (string) $booking->shares, 2);
        });

        static::updating(function (QurbaniBooking $booking): void {
            if ($booking->isDirty(['booking_no', 'season_id', 'offering_id', 'donor_id', 'shares', 'price_per_share'])) {
                throw new DomainException('A booking cannot be changed. Cancel it and book again.');
            }

            if ($booking->getRawOriginal('status') !== QurbaniBookingStatus::Pending->value && $booking->isDirty('status')) {
                throw new DomainException('A confirmed or cancelled booking keeps its status.');
            }
        });
    }

    /**
     * Bookings that use up shares: confirmed, or pending while the donor pays — within the
     * hold time, or with a donation that is still open (e.g. a bank transfer being checked).
     *
     * @param  Builder<QurbaniBooking>  $query
     */
    public function scopeHolding(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('status', QurbaniBookingStatus::Confirmed->value)
            ->orWhere(fn (Builder $pending) => $pending
                ->where('status', QurbaniBookingStatus::Pending->value)
                ->where(fn (Builder $held) => $held
                    ->where('hold_until', '>', now())
                    ->orWhereHas('donations', fn (Builder $d) => $d->where('status', DonationStatus::Pending->value)))));
    }

    /** Waiting for payment and still holding its shares. */
    public function isPayable(): bool
    {
        if ($this->status !== QurbaniBookingStatus::Pending) {
            return false;
        }

        return ($this->hold_until !== null && $this->hold_until->isFuture())
            || $this->donations()->where('status', DonationStatus::Pending->value)->exists();
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return $this->shareRows->pluck('name')->values()->all();
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
     * @return BelongsTo<Donor, $this>
     */
    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class)->withTrashed();
    }

    /**
     * The names, one per share.
     *
     * @return HasMany<QurbaniShare, $this>
     */
    public function shareRows(): HasMany
    {
        return $this->hasMany(QurbaniShare::class, 'booking_id')->orderBy('position');
    }

    /**
     * Every donation started for this booking (a failed attempt, then a paid one).
     *
     * @return HasMany<Donation, $this>
     */
    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class, 'qurbani_booking_id');
    }

    /**
     * The donation that paid it, or the one in progress.
     *
     * @return HasOne<Donation, $this>
     */
    public function donation(): HasOne
    {
        return $this->hasOne(Donation::class, 'qurbani_booking_id')->latestOfMany();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shares' => 'integer',
            'price_per_share' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'status' => QurbaniBookingStatus::class,
            'channel' => DonationChannel::class,
            'hold_until' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
