<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** How a disbursement reaches the beneficiary or project. */
enum PayoutMethod: string implements HasLabel
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Wallet = 'wallet';
    case InKind = 'in_kind';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Bank => 'Bank transfer',
            self::Wallet => 'JazzCash / Easypaisa',
            self::InKind => 'In kind (goods)',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /** Bank and wallet payouts need the account the money goes to. */
    public function needsAccount(): bool
    {
        return in_array($this, [self::Bank, self::Wallet], true);
    }
}
