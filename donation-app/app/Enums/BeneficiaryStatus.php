<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A beneficiary's standing (Week 9). Registered by a field officer; verified after a
 * visit confirms their circumstances; inactive when help is no longer needed (or they
 * moved / passed away). Inactive people keep their history.
 */
enum BeneficiaryStatus: string implements HasColor, HasLabel
{
    case Registered = 'registered';
    case Verified = 'verified';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Registered',
            self::Verified => 'Verified',
            self::Inactive => 'Inactive',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Registered => 'warning',
            self::Verified => 'success',
            self::Inactive => 'gray',
        };
    }
}
