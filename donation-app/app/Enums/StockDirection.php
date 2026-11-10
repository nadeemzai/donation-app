<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StockDirection: string implements HasColor, HasLabel
{
    case In = 'in';
    case Out = 'out';
    case Adjust = 'adjust';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Stock in',
            self::Out => 'Stock out',
            self::Adjust => 'Count correction',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::In => 'success',
            self::Out => 'warning',
            self::Adjust => 'gray',
        };
    }
}
