<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReceiptFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A donation receipt (FR-RCP-01). Issued only by ReceiptService.
 * Number, donation, amount and snapshot never change; receipts are never deleted.
 *
 * @property int $id
 * @property string $receipt_no
 * @property int $donation_id
 * @property string $amount
 * @property string $currency
 * @property array<string, mixed> $snapshot
 * @property Carbon $issued_at
 * @property int|null $issued_by
 * @property string $disk
 * @property string|null $path
 * @property string|null $file_hash
 * @property Carbon|null $rendered_at
 * @property int $render_count
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Donation $donation
 */
#[Fillable(['receipt_no', 'donation_id', 'amount', 'currency', 'snapshot', 'issued_at', 'issued_by', 'disk'])]
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory;

    public const FROZEN = ['receipt_no', 'donation_id', 'amount', 'currency', 'snapshot', 'issued_at'];

    protected $attributes = [
        'currency' => 'PKR',
        'disk' => 'local',
        'render_count' => 0,
    ];

    protected static function booted(): void
    {
        static::updating(function (Receipt $receipt): void {
            if ($receipt->isDirty(self::FROZEN)) {
                throw new DomainException('An issued receipt can never be changed. Void it instead.');
            }
        });

        static::deleting(function (): void {
            throw new DomainException('Receipts are never deleted. Void it instead.');
        });
    }

    public function isRendered(): bool
    {
        return $this->path !== null;
    }

    public function isVoid(): bool
    {
        return $this->voided_at !== null;
    }

    public function fileName(): string
    {
        return $this->receipt_no.'.pdf';
    }

    /**
     * @return BelongsTo<Donation, $this>
     */
    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'snapshot' => 'array',
            'issued_at' => 'datetime',
            'rendered_at' => 'datetime',
            'render_count' => 'integer',
            'voided_at' => 'datetime',
        ];
    }
}
