<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InventoryCategory: string implements HasLabel
{
    case Food = 'food';
    case Clothing = 'clothing';
    case Medical = 'medical';
    case Education = 'education';
    case Household = 'household';
    case Books = 'books';
    case Toys = 'toys';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Food => 'Food and ration',
            self::Clothing => 'Clothing and blankets',
            self::Medical => 'Medicine and medical',
            self::Education => 'School supplies',
            self::Household => 'Household',
            self::Books => 'Books (new and used)',
            self::Toys => 'Toys and baby items',
            self::Other => 'Other',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
