<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Pakistani mobile number → WhatsApp chat id for Green API.
 *   0300-1234567 / +92 300 1234567 / 923001234567  →  923001234567@c.us
 * Landlines and anything that isn't a valid 03XX mobile number give null.
 */
final class WhatsAppNumber
{
    public static function chatId(?string $phone): ?string
    {
        if (! PakistanPhone::isValid($phone)) {
            return null;
        }

        return '92'.substr((string) PakistanPhone::normalize($phone), 1).'@c.us';
    }
}
