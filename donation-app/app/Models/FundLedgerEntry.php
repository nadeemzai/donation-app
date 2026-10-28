<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LedgerEntryType;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One line in a fund's ledger (FR-FIN-01). Append-only: written only by
 * FundLedgerService, and can never be updated or deleted (CLAUDE.md rule 3).
 *
 * @property int $id
 * @property int $fund_id
 * @property LedgerEntryType $entry_type
 * @property string $amount
 * @property string $balance_after
 * @property string $source_type
 * @property int $source_id
 * @property string $description
 * @property int|null $posted_by
 * @property Carbon $posted_at
 * @property Carbon|null $created_at
 * @property-read Fund $fund
 */
#[Fillable(['fund_id', 'entry_type', 'amount', 'balance_after', 'source_type', 'source_id', 'description', 'posted_by', 'posted_at'])]
class FundLedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new DomainException('Ledger entries can never be changed. Post a reversal instead.');
        });

        static::deleting(function (): void {
            throw new DomainException('Ledger entries can never be deleted.');
        });
    }

    /**
     * @return BelongsTo<Fund, $this>
     */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class)->withTrashed();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /** Signed amount: + for credits, − for debits. */
    public function signedAmount(): string
    {
        return $this->entry_type === LedgerEntryType::Credit
            ? (string) $this->amount
            : bcmul((string) $this->amount, '-1', 2);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_type' => LedgerEntryType::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }
}
