<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** FR-QRB-03 (Day 2): available → allocated → slaughtered → distributed. */
enum QurbaniAnimalStatus: string implements HasColor, HasLabel
{
    case Available = 'available';
    case Allocated = 'allocated';
    case Slaughtered = 'slaughtered';
    case Distributed = 'distributed';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Allocated => 'Allocated',
            self::Slaughtered => 'Slaughtered',
            self::Distributed => 'Meat distributed',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Available => 'gray',
            self::Allocated => 'info',
            self::Slaughtered => 'warning',
            self::Distributed => 'success',
        };
    }
}
