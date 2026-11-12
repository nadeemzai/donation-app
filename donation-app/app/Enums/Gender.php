<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Gender: string implements HasLabel
{
    case Female = 'female';
    case Male = 'male';

    public function getLabel(): string
    {
        return match ($this) {
            self::Female => 'Female',
            self::Male => 'Male',
        };
    }
}
