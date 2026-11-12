<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Money into a fund (credit) or out of it (debit) — FR-FIN-01. */
enum LedgerEntryType: string implements HasColor, HasLabel
{
    case Credit = 'credit';
    case Debit = 'debit';

    public function getLabel(): string
    {
        return $this === self::Credit ? 'In' : 'Out';
    }

    public function getColor(): string
    {
        return $this === self::Credit ? 'success' : 'danger';
    }
}
