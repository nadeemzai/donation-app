<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** One due date of a pledge (Week 8 Day 2). A missed period can still be paid late. */
enum PledgePeriodStatus: string implements HasColor, HasLabel
{
    case Due = 'due';
    case Paid = 'paid';
    case Missed = 'missed';

    public function label(): string
    {
        return match ($this) {
            self::Due => 'Due',
            self::Paid => 'Paid',
            self::Missed => 'Missed',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Due => 'warning',
            self::Paid => 'success',
            self::Missed => 'gray',
        };
    }
}
