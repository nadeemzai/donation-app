<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Donor mobile numbers, in Pakistan and abroad (overseas donors).
 *
 *   Pakistani mobile  "0300-1234567", "+92 300 1234567", "923001234567" → "03001234567"
 *   Other countries   "+44 7700 900123", "0044 7700 900123"            → "+447700900123" (E.164)
 *
 * A number abroad must start with + or 00 and its country code, so it can never be mistaken
 * for a local number. Pakistani landlines are refused (no WhatsApp, no wallet).
 */
final class Phone
{
    public const EXAMPLE = '0300-1234567, or +44 7700 900123 from abroad';

    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        if (PakistanPhone::isValid($phone)) {
            return PakistanPhone::normalize($phone);
        }

        $trimmed = trim($phone);
        $digits = preg_replace('/\D/', '', $trimmed) ?? '';

        if (str_starts_with($trimmed, '00')) {
            $digits = substr($digits, 2);
        } elseif (! str_starts_with($trimmed, '+')) {
            return null; // no country code: we can't tell where it is
        }

        // E.164: country code + number, 8–15 digits, never starting with 0.
        if (preg_match('/^[1-9]\d{7,14}$/', $digits) !== 1 || str_starts_with($digits, '92')) {
            return null; // +92 that isn't a mobile is a Pakistani landline
        }

        return '+'.$digits;
    }

    public static function isValid(?string $phone): bool
    {
        return self::normalize($phone) !== null;
    }

    /** Needed for JazzCash and Easypaisa. */
    public static function isPakistaniMobile(?string $phone): bool
    {
        return PakistanPhone::isValid($phone);
    }

    public static function isOverseas(?string $phone): bool
    {
        return str_starts_with((string) self::normalize($phone), '+');
    }

    /** Green API chat id, e.g. "923001234567@c.us" or "447700900123@c.us"; null when not a mobile. */
    public static function whatsappChatId(?string $phone): ?string
    {
        $normalized = self::normalize($phone);

        if ($normalized === null) {
            return null;
        }

        return (str_starts_with($normalized, '+') ? substr($normalized, 1) : '92'.substr($normalized, 1)).'@c.us';
    }
}
