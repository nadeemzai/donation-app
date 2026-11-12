<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Mobile number → WhatsApp chat id for Green API, in Pakistan and abroad.
 *   0300-1234567 / +92 300 1234567 / 923001234567  →  923001234567@c.us
 *   +44 7700 900123                                →  447700900123@c.us
 * Landlines and numbers without a country code (outside Pakistan) give null.
 */
final class WhatsAppNumber
{
    public static function chatId(?string $phone): ?string
    {
        return Phone::whatsappChatId($phone);
    }
}
