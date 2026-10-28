<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\WebhookOutcome;
use App\Payments\DTOs\CallbackPayload;
use Database\Factories\PaymentWebhookLogFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A gateway callback, stored word for word before processing (CLAUDE.md rule 8).
 * What arrived (payload, fields, headers, ip, url) can never change; only the outcome
 * columns are filled in afterwards. Never deleted.
 *
 * @property int $id
 * @property PaymentMethod $method
 * @property int|null $payment_id
 * @property string|null $reference
 * @property string|null $gateway_ref
 * @property string $http_method
 * @property string $url
 * @property string|null $ip
 * @property array<string, mixed>|null $headers
 * @property string $payload
 * @property array<string, mixed>|null $fields
 * @property bool|null $signature_valid
 * @property WebhookOutcome $outcome
 * @property string|null $error
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Payment|null $payment
 */
#[Fillable(['method', 'http_method', 'url', 'ip', 'headers', 'payload', 'fields', 'reference', 'gateway_ref'])]
class PaymentWebhookLog extends Model
{
    /** @use HasFactory<PaymentWebhookLogFactory> */
    use HasFactory;

    /** What arrived — frozen once stored. */
    public const FROZEN = ['method', 'http_method', 'url', 'ip', 'headers', 'payload', 'fields'];

    /** Never stored: these can carry session cookies or credentials. */
    private const DROPPED_HEADERS = ['authorization', 'cookie', 'set-cookie', 'proxy-authorization', 'x-csrf-token', 'x-xsrf-token'];

    protected $attributes = [
        'outcome' => 'received',
    ];

    protected static function booted(): void
    {
        static::updating(function (PaymentWebhookLog $log): void {
            if ($log->isDirty(self::FROZEN)) {
                throw new DomainException('A stored callback can never be changed.');
            }
        });

        static::deleting(function (): void {
            throw new DomainException('Callback logs are never deleted.');
        });
    }

    /** Store a callback exactly as it arrived (minus auth/cookie headers). Call this first. */
    public static function record(CallbackPayload $payload): self
    {
        $headers = array_diff_key(
            array_change_key_case($payload->headers, CASE_LOWER),
            array_flip(self::DROPPED_HEADERS),
        );

        return self::query()->create([
            'method' => $payload->method,
            'http_method' => $payload->httpMethod,
            'url' => mb_substr($payload->url, 0, 500),
            'ip' => $payload->ip,
            'headers' => $headers,
            'payload' => $payload->rawBody,
            'fields' => $payload->fields,
        ]);
    }

    public function markOutcome(WebhookOutcome $outcome, ?Payment $payment = null, ?string $error = null): void
    {
        $this->outcome = $outcome;
        $this->payment_id = $payment->id ?? $this->payment_id;
        $this->error = $error;
        $this->processed_at = now();
        $this->save();
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'outcome' => WebhookOutcome::class,
            'headers' => 'array',
            'fields' => 'array',
            'signature_valid' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }
}
