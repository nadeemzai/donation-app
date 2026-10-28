<?php

declare(strict_types=1);

namespace App\Payments\DTOs;

/**
 * What a gateway needs to start a payment. Built by PaymentService from the donation.
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
    ) {}
}
