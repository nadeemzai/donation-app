<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InventoryUnit: string implements HasLabel
{
    case Piece = 'piece';
    case Kg = 'kg';
    case Litre = 'litre';
    case Pack = 'pack';
    case Bag = 'bag';
    case Box = 'box';
    case Set = 'set';

    public function label(): string
    {
        return match ($this) {
            self::Piece => 'Piece',
            self::Kg => 'Kg',
            self::Litre => 'Litre',
            self::Pack => 'Pack',
            self::Bag => 'Bag',
            self::Box => 'Box',
            self::Set => 'Set',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
