<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Pakistani mobile numbers. Stored as 03XXXXXXXXX (11 digits).
 * Accepts 0300-1234567, 0300 1234567, +92 300 1234567, 92300…
 */
final class PakistanPhone
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '92') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }

    public static function isValid(?string $phone): bool
    {
        $normalized = self::normalize($phone);

        return $normalized !== null && preg_match('/^03\d{9}$/', $normalized) === 1;
    }
}
