<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\PaymentMethod;
use App\Enums\SequenceKey;
use App\Models\Concerns\Auditable;
use App\Services\NumberSequenceService;
use Database\Factories\DonationFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * A single gift (FR-DON-01).
 *
 * Rules enforced here, whatever screen or API saves the record:
 * - BR-01: belongs to exactly one fund; a campaign donation goes to the campaign's fund.
 * - BR-04: donation_no is issued from the number sequence, never from the id.
 * - BR-05: once settled (paid / refunded), amount, fund, campaign, donor and method are frozen.
 * - Donations are never deleted.
 * - status, refunded_amount and the *_at / confirmed_by fields change only through
 *   DonationService — they are not mass-assignable.
 * - Refunds are separate DonationRefund records; amount never changes (BR-05).
 *
 * @property int $id
 * @property string $donation_no
 * @property int $donor_id
 * @property int $fund_id
 * @property int|null $campaign_id
 * @property string $amount
 * @property string $refunded_amount
 * @property string $currency
 * @property DonationStatus $status
 * @property DonationChannel $channel
 * @property PaymentMethod $payment_method
 * @property string|null $reference
 * @property bool $is_anonymous
 * @property string|null $dedication
 * @property string|null $donor_note
 * @property string|null $staff_note
 * @property Carbon|null $received_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $cancelled_at
 * @property int|null $recorded_by
 * @property int|null $confirmed_by
 * @property string|null $idempotency_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Donor $donor
 * @property-read Fund $fund
 * @property-read Campaign|null $campaign
 */
#[Fillable([
    'donor_id', 'fund_id', 'campaign_id', 'amount', 'channel', 'payment_method', 'reference',
    'is_anonymous', 'dedication', 'donor_note', 'staff_note', 'received_at', 'recorded_by', 'idempotency_key',
])]
class Donation extends Model
{
    /** @use HasFactory<DonationFactory> */
    use Auditable, HasFactory;

    /** Frozen once the donation is settled (BR-05). */
    public const FROZEN_WHEN_SETTLED = ['donor_id', 'fund_id', 'campaign_id', 'amount', 'currency', 'payment_method', 'channel'];

    protected $attributes = [
        'status' => 'pending',
        'refunded_amount' => '0.00',
        'currency' => 'PKR',
        'is_anonymous' => false,
    ];

    protected static function booted(): void
    {
        static::saving(function (Donation $donation): void {
            if ($donation->campaign_id !== null) {
                $campaignFund = Campaign::withTrashed()->whereKey($donation->campaign_id)->value('fund_id');

                if ($campaignFund === null) {
                    throw new DomainException('Campaign not found.');
                }

                // BR-01: a campaign donation always goes to that campaign's fund.
                if ($donation->getAttribute('fund_id') === null) {
                    $donation->fund_id = (int) $campaignFund;
                }

                if ((int) $donation->fund_id !== (int) $campaignFund) {
                    throw new DomainException("This campaign's donations go to its own fund only.");
                }
            }

            if (bccomp((string) $donation->amount, '0', 2) <= 0) {
                throw new DomainException('The donation amount must be more than zero.');
            }
        });

        static::creating(function (Donation $donation): void {
            if (blank($donation->getAttribute('donation_no'))) {
                $donation->donation_no = app(NumberSequenceService::class)->next(SequenceKey::Donation);
            }
        });

        static::updating(function (Donation $donation): void {
            $originalStatus = DonationStatus::from((string) $donation->getRawOriginal('status'));

            // BR-05: a settled donation's core facts can never change.
            if ($originalStatus->isSettled() && $donation->isDirty(self::FROZEN_WHEN_SETTLED)) {
                throw new DomainException('A paid donation cannot be changed. Record a refund instead.');
            }

            if ($donation->isDirty('donation_no')) {
                throw new DomainException('A donation number can never change.');
            }
        });

        static::deleting(function (): void {
            throw new DomainException('Donations are never deleted. Cancel or refund it instead.');
        });
    }

    /**
     * @return BelongsTo<Donor, $this>
     */
    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Fund, $this>
     */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * @return HasMany<DonationRefund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(DonationRefund::class)->orderBy('id');
    }

    /**
     * @return MorphMany<FundLedgerEntry, $this>
     */
    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(FundLedgerEntry::class, 'source');
    }

    /** Amount still counted after refunds. */
    public function netAmount(): string
    {
        return bcsub((string) $this->amount, (string) $this->refunded_amount, 2);
    }

    public function isPaid(): bool
    {
        return $this->status === DonationStatus::Paid;
    }

    /** Name for public surfaces (BR-08): anonymous donors are never named. */
    public function publicDonorName(): string
    {
        return $this->is_anonymous ? 'Anonymous' : $this->donor->name;
    }

    /**
     * Money that counts: paid or partly refunded.
     *
     * @param  Builder<Donation>  $query
     */
    public function scopeCounted(Builder $query): void
    {
        $query->whereIn('status', [DonationStatus::Paid->value, DonationStatus::PartiallyRefunded->value]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'status' => DonationStatus::class,
            'channel' => DonationChannel::class,
            'payment_method' => PaymentMethod::class,
            'is_anonymous' => 'boolean',
            'received_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
