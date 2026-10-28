<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One attempt to pay a donation through a gateway (FR-PAY-01/02).
 *
 * - Created and changed only by PaymentService. status, gateway_ref, response_* and the
 *   timestamps are not mass-assignable.
 * - donation, method, amount, currency and reference never change once created.
 * - Never deleted.
 *
 * @property int $id
 * @property int $donation_id
 * @property PaymentMethod $method
 * @property PaymentStatus $status
 * @property string $amount
 * @property string $currency
 * @property string $reference
 * @property string|null $gateway_ref
 * @property string $mode
 * @property string|null $response_code
 * @property string|null $response_message
 * @property string|null $review_reason
 * @property Carbon $initiated_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $last_checked_at
 * @property int $check_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Donation $donation
 */
#[Fillable(['donation_id', 'method', 'amount', 'currency', 'reference', 'mode', 'initiated_at', 'expires_at'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    public const FROZEN = ['donation_id', 'method', 'amount', 'currency', 'reference'];

    protected $attributes = [
        'status' => 'initiated',
        'currency' => 'PKR',
        'check_count' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            if (blank($payment->getAttribute('reference'))) {
                $payment->reference = self::newReference();
            }

            $payment->initiated_at ??= now();
        });

        static::updating(function (Payment $payment): void {
            if ($payment->isDirty(self::FROZEN)) {
                throw new DomainException('A payment\'s donation, method, amount and reference can never change.');
            }
        });

        static::deleting(function (): void {
            throw new DomainException('Payments are never deleted.');
        });
    }

    /**
     * Our transaction reference: "T" + yymmddHHiiss + 5 random letters/digits = 18 characters,
     * within JazzCash's 20-character, letters-and-digits-only limit.
     */
    public static function newReference(): string
    {
        return 'T'.now()->format('ymdHis').strtoupper(Str::random(5));
    }

    /**
     * @return BelongsTo<Donation, $this>
     */
    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    /**
     * @return HasMany<PaymentWebhookLog, $this>
     */
    public function webhookLogs(): HasMany
    {
        return $this->hasMany(PaymentWebhookLog::class)->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'initiated_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'check_count' => 'integer',
        ];
    }
}
