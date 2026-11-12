<?php

declare(strict_types=1);

namespace App\Payments\Gateways\JazzCash;

/**
 * JazzCash pp_SecureHash.
 *
 *   1. Take every pp_* and ppmpf_* field except pp_SecureHash, skipping empty values.
 *   2. Sort them by field name (ASCII).
 *   3. Join the values with "&", with the integrity salt in front: "salt&v1&v2&…".
 *   4. HMAC-SHA256 of that string, keyed with the integrity salt; upper-case hex.
 *
 * Verification compares case-insensitively and in constant time.
 */
final class JazzCashHash
{
    /**
     * @param  array<string, mixed>  $fields
     */
    public static function make(array $fields, string $salt): string
    {
        $signed = [];

        foreach ($fields as $name => $value) {
            if ($name === 'pp_SecureHash' || (! str_starts_with($name, 'pp_') && ! str_starts_with($name, 'ppmpf_'))) {
                continue;
            }

            if (! is_scalar($value) || (string) $value === '') {
                continue;
            }

            $signed[$name] = (string) $value;
        }

        ksort($signed, SORT_STRING);

        return strtoupper(hash_hmac('sha256', $salt.'&'.implode('&', $signed), $salt));
    }

    /**
     * @param  array<string, mixed>  $fields  must include pp_SecureHash
     */
    public static function verify(array $fields, string $salt): bool
    {
        $given = $fields['pp_SecureHash'] ?? null;

        if (! is_string($given) || $given === '') {
            return false;
        }

        return hash_equals(self::make($fields, $salt), strtoupper($given));
    }
}
