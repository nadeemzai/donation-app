<?php

declare(strict_types=1);

namespace App\Payments\DTOs;

/**
 * What a gateway needs to start a payment. Built by PaymentService from the donation.
 *
 * directWallet = charge the donor's wallet straight from our site/app (they approve on
 * their phone) instead of sending them to the gateway's hosted page. cnicLast6 is only
 * passed through to the gateway — it is never stored or logged.
 */
final readonly class PaymentRequest
{
    public function __construct(
        public string $amount,
        public string $currency,
        public string $description,
        public string $returnUrl,
        public ?string $mobileNumber = null,
        public ?string $email = null,
        public bool $directWallet = false,
        public ?string $cnicLast6 = null,
    ) {}
}
