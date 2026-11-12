<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Fund;
use App\Support\Money;
use DomainException;

/**
 * A debit would take a fund below zero (BR-07). The message is safe to show staff.
 */
class InsufficientFundBalance extends DomainException
{
    public function __construct(
        public readonly Fund $fund,
        public readonly string $available,
        public readonly string $requested,
    ) {
        parent::__construct(sprintf(
            'Not enough money in %s: %s available, %s needed.',
            $fund->name,
            Money::rupees($available),
            Money::rupees($requested),
        ));
    }
}
