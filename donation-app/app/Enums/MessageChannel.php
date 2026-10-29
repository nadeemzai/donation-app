<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MessageChannel: string implements HasLabel
{
    case WhatsApp = 'whatsapp';
    case Email = 'email';
    case Sms = 'sms';

    public function getLabel(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Email => 'Email',
            self::Sms => 'SMS',
        };
    }
}
