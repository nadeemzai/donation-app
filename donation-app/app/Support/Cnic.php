<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Pakistani CNIC helpers. CNICs are stored encrypted; the HMAC hash lets us
 * enforce uniqueness and look donors/beneficiaries up without decrypting.
 */
final class Cnic
{
    /** Digits only: "35202-1234567-1" → "3520212345671". */
    public static function normalize(string $cnic): string
    {
        return preg_replace('/\D/', '', $cnic) ?? '';
    }

    public static function isValid(string $cnic): bool
    {
        return strlen(self::normalize($cnic)) === 13;
    }

    public static function hash(string $cnic): string
    {
        return hash_hmac('sha256', self::normalize($cnic), (string) config('app.key'));
    }
}
