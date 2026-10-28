<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Fund;
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
            'Not enough money in %s: Rs %s available, Rs %s needed.',
            $fund->name,
            self::rupees($available),
            self::rupees($requested),
        ));
    }

    /** "1234567.50" → "1,234,567.50" without going through float. */
    private static function rupees(string $amount): string
    {
        [$whole, $paisa] = explode('.', bcadd($amount, '0', 2));
        $sign = str_starts_with($whole, '-') ? '-' : '';
        $whole = ltrim($whole, '-');

        return $sign.strrev(implode(',', str_split(strrev($whole), 3))).'.'.$paisa;
    }
}
