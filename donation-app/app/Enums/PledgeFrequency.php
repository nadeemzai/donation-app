<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** How often a pledge falls due (Week 8). */
enum PledgeFrequency: string implements HasLabel
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Every month',
            self::Quarterly => 'Every 3 months',
            self::Yearly => 'Every year',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Yearly => 12,
        };
    }
}
