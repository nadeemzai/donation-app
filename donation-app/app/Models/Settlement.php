<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SettlementMatch;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One imported gateway settlement file (Week 11 Day 4, FR-FIN-02). Written and re-matched only
 * by SettlementService. The gateway, date and file never change; never deleted.
 *
 * @property int $id
 * @property PaymentMethod $gateway
 * @property Carbon $settlement_date
 * @property string $file_name
 * @property string $file_path
 * @property string $file_hash
 * @property int $row_count
 * @property int $matched_count
 * @property int $problem_count
 * @property int $ignored_count
 * @property string $gross_total
 * @property string $fee_total
 * @property string $net_total
 * @property string|null $notes
 * @property int|null $imported_by
 * @property Carbon $imported_at
 * @property Carbon|null $last_matched_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $importer
 */
class Settlement extends Model
{
    public const FROZEN = ['gateway', 'settlement_date', 'file_name', 'file_path', 'file_hash'];

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (Settlement $settlement): void {
            if ($settlement->isDirty(self::FROZEN)) {
                throw new DomainException('A settlement file cannot be changed. Import the corrected file instead.');
            }
        });

        static::deleting(fn () => throw new DomainException('Settlement files are never deleted.'));
    }

    public function label(): string
    {
        return $this->gateway->label().' · '.$this->settlement_date->format('d M Y');
    }

    /** Nothing flagged is left open. (Not isClean() — that name belongs to Eloquent.) */
    public function isReconciled(): bool
    {
        return $this->problem_count === 0;
    }

    /**
     * @return HasMany<SettlementLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SettlementLine::class)->orderBy('line_no');
    }

    /**
     * Flagged lines nobody has resolved yet.
     *
     * @return HasMany<SettlementLine, $this>
     */
    public function openProblems(): HasMany
    {
        return $this->hasMany(SettlementLine::class)
            ->whereIn('match_status', SettlementMatch::problemValues())
            ->whereNull('resolved_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gateway' => PaymentMethod::class,
            'settlement_date' => 'date',
            'gross_total' => 'decimal:2',
            'fee_total' => 'decimal:2',
            'net_total' => 'decimal:2',
            'imported_at' => 'datetime',
            'last_matched_at' => 'datetime',
        ];
    }
}
