<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use Database\Factories\DonationRefundFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * Money given back on a paid donation (FR-DON-02, BR-05). Created only by
 * DonationService::refund(), which also posts the matching ledger debit.
 * Never updated or deleted — a refund made by mistake is a matter for a new donation.
 *
 * @property int $id
 * @property int $donation_id
 * @property int $fund_id
 * @property string $amount
 * @property PaymentMethod $method
 * @property string|null $reference
 * @property string $reason
 * @property int|null $refunded_by
 * @property Carbon $refunded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Donation $donation
 * @property-read Fund $fund
 */
#[Fillable(['donation_id', 'fund_id', 'amount', 'method', 'reference', 'reason', 'refunded_by', 'refunded_at'])]
class DonationRefund extends Model
{
    /** @use HasFactory<DonationRefundFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new DomainException('A refund can never be changed.');
        });

        static::deleting(function (): void {
            throw new DomainException('A refund can never be deleted.');
        });
    }

    /**
     * @return BelongsTo<Donation, $this>
     */
    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    /**
     * @return BelongsTo<Fund, $this>
     */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function refunder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    /**
     * @return MorphOne<FundLedgerEntry, $this>
     */
    public function ledgerEntry(): MorphOne
    {
        return $this->morphOne(FundLedgerEntry::class, 'source');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'refunded_at' => 'datetime',
        ];
    }
}
