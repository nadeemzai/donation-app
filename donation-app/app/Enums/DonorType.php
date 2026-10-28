<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DonorType: string implements HasLabel
{
    case Individual = 'individual';
    case Corporate = 'corporate';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individual',
            self::Corporate => 'Corporate',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
