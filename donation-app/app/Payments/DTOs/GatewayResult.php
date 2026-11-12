<?php

declare(strict_types=1);

namespace App\Payments\DTOs;

use App\Enums\GatewayOutcome;

/**
 * A gateway's verified answer about one payment.
 *
 * reference = OUR payment reference. gatewayRef = the gateway's transaction id.
 * amount is a decimal string ("1500.00") — adapters convert paisa etc. before returning.
 */
final readonly class GatewayResult
{
    /**
     * @param  array<string, mixed>  $raw  the gateway's fields, secrets removed
     */
    public function __construct(
        public GatewayOutcome $outcome,
        public string $reference,
        public ?string $gatewayRef = null,
        public ?string $amount = null,
        public ?string $currency = null,
        public ?string $responseCode = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}
}
