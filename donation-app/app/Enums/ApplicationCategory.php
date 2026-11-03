<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** What a beneficiary is asking for (FR-BEN-02). */
enum ApplicationCategory: string implements HasLabel
{
    case Ration = 'ration';
    case Medical = 'medical';
    case Education = 'education';
    case Housing = 'housing';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Ration => 'Ration / food',
            self::Medical => 'Medical',
            self::Education => 'Education',
            self::Housing => 'Housing / rent',
            self::Other => 'Other',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
