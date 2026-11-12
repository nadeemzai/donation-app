<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Small helpers for PKR amounts held as decimal strings (CLAUDE.md rule 1 — never float).
 */
final class Money
{
    /**
     * "1500" / "1500.5" / "1500.50" → "1500.50". Anything else (negative, 3 decimals,
     * commas, text) is rejected.
     */
    public static function normalise(string $amount): string
    {
        if (preg_match('/^\d{1,12}(\.\d{1,2})?$/', $amount) !== 1) {
            throw new InvalidArgumentException("Invalid amount [{$amount}].");
        }

        return bcadd($amount, '0', 2);
    }

    /** "1234567.5" → "1,234,567.50" — for messages and screens. */
    public static function format(string $amount): string
    {
        [$whole, $paisa] = explode('.', bcadd($amount, '0', 2));
        $sign = str_starts_with($whole, '-') ? '-' : '';
        $whole = ltrim($whole, '-');

        return $sign.strrev(implode(',', str_split(strrev($whole), 3))).'.'.$paisa;
    }

    /** "Rs 1,500.00" */
    public static function rupees(string $amount): string
    {
        return 'Rs '.self::format($amount);
    }
}
