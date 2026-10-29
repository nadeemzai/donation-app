<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Rupee amounts in words, Pakistani style (thousand, lakh, crore, arab):
 *   "12500.50" → "Twelve Thousand Five Hundred Rupees and Fifty Paisa Only"
 *   "1500000"  → "Fifteen Lakh Rupees Only"
 * Works on the decimal string — no floats.
 */
final class AmountInWords
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    /** Largest first: [value, name]. */
    private const SCALES = [[1_000_000_000, 'Arab'], [10_000_000, 'Crore'], [100_000, 'Lakh'], [1_000, 'Thousand'], [100, 'Hundred']];

    public static function rupees(string $amount): string
    {
        $normalised = Money::normalise($amount); // rejects negatives, commas, 3+ decimals
        [$whole, $paisa] = explode('.', $normalised);

        $rupees = (int) $whole;
        $paisaValue = (int) $paisa;

        if ($rupees === 0 && $paisaValue === 0) {
            return 'Zero Rupees Only';
        }

        $parts = [];

        if ($rupees > 0) {
            $parts[] = self::words($rupees).' '.($rupees === 1 ? 'Rupee' : 'Rupees');
        }

        if ($paisaValue > 0) {
            $parts[] = self::words($paisaValue).' Paisa';
        }

        return implode(' and ', $parts).' Only';
    }

    public static function words(int $number): string
    {
        if ($number < 0) {
            throw new InvalidArgumentException('Negative amounts have no words here.');
        }

        if ($number === 0) {
            return 'Zero';
        }

        $words = [];

        foreach (self::SCALES as [$value, $name]) {
            if ($number >= $value) {
                $words[] = self::words(intdiv($number, $value)).' '.$name;
                $number %= $value;
            }
        }

        if ($number > 0) {
            $words[] = $number < 20
                ? self::ONES[$number]
                : trim(self::TENS[intdiv($number, 10)].' '.self::ONES[$number % 10]);
        }

        return implode(' ', $words);
    }
}
