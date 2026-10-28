<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What kind of money a fund holds (FR-CMP-01). Zakat must be kept separate
 * and only spent on eligible beneficiaries (BR-02).
 */
enum FundType: string implements HasColor, HasLabel
{
    case Zakat = 'zakat';
    case Sadqa = 'sadqa';
    case Fitrana = 'fitrana';
    case Fidya = 'fidya';
    case General = 'general';
    case Restricted = 'restricted';

    public function label(): string
    {
        return match ($this) {
            self::Zakat => 'Zakat',
            self::Sadqa => 'Sadqa / Atiya',
            self::Fitrana => 'Fitrana',
            self::Fidya => 'Fidya',
            self::General => 'General',
            self::Restricted => 'Restricted',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Zakat => 'success',
            self::Restricted => 'warning',
            default => 'info',
        };
    }

    public function isZakat(): bool
    {
        return $this === self::Zakat;
    }

    /** Restricted funds must say what the money may be spent on (BR-10). */
    public function requiresRestriction(): bool
    {
        return $this === self::Restricted;
    }
}
