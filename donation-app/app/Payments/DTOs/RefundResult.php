<?php

declare(strict_types=1);

namespace App\Payments\DTOs;

final readonly class RefundResult
{
    public function __construct(
        public bool $succeeded,
        public ?string $gatewayRef = null,
        public ?string $message = null,
    ) {}
}
