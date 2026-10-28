<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Where a donation was made (FR-DON-01). */
enum DonationChannel: string implements HasLabel
{
    case Web = 'web';
    case Mobile = 'mobile';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::Web => 'Website',
            self::Mobile => 'Mobile app',
            self::Admin => 'Recorded by staff',
        };
    }
}
