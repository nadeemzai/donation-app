<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SettlementMatch;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row of a settlement file. What the file said never changes; the match result changes only
 * through SettlementService (re-match, resolve). Never deleted.
 *
 * @property int $id
 * @property int $settlement_id
 * @property int $line_no
 * @property string|null $our_reference
 * @property string|null $gateway_ref
 * @property string|null $amount
 * @property string|null $fee
 * @property string|null $net
 * @property Carbon|null $transacted_at
 * @property string|null $gateway_status
 * @property array<string, string> $raw
 * @property int|null $payment_id
 * @property SettlementMatch $match_status
 * @property string|null $mismatch_reason
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property string|null $resolution_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Settlement $settlement
 * @property-read Payment|null $payment
 * @property-read User|null $resolver
 */
class SettlementLine extends Model
{
    public const FROZEN = ['settlement_id', 'line_no', 'our_reference', 'gateway_ref', 'amount', 'fee', 'net', 'transacted_at', 'gateway_status', 'raw'];

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (SettlementLine $line): void {
            if ($line->isDirty(self::FROZEN)) {
                throw new DomainException('What a settlement file says can never be changed.');
            }
        });

        static::deleting(fn () => throw new DomainException('Settlement lines are never deleted.'));
    }

    public function isOpenProblem(): bool
    {
        return $this->match_status->isProblem() && $this->resolved_at === null;
    }

    /**
     * @return BelongsTo<Settlement, $this>
     */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'net' => 'decimal:2',
            'transacted_at' => 'datetime',
            'raw' => 'array',
            'match_status' => SettlementMatch::class,
            'resolved_at' => 'datetime',
        ];
    }
}
