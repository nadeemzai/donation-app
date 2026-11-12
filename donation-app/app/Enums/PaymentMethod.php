<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How money arrives (FR-DON-01, FR-PAY-*). Card payments are Phase 2.
 */
enum PaymentMethod: string implements HasLabel
{
    case JazzCash = 'jazzcash';
    case Easypaisa = 'easypaisa';
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Cheque = 'cheque';

    public function label(): string
    {
        return match ($this) {
            self::JazzCash => 'JazzCash',
            self::Easypaisa => 'Easypaisa',
            self::BankTransfer => 'Bank transfer / Raast',
            self::Cash => 'Cash',
            self::Cheque => 'Cheque',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /** Confirmed automatically by a gateway (Week 4). */
    public function isGateway(): bool
    {
        return in_array($this, [self::JazzCash, self::Easypaisa], true);
    }

    /** Recorded by staff with a reference / proof. */
    public function isOffline(): bool
    {
        return ! $this->isGateway();
    }

    /** @return list<self> */
    public static function offline(): array
    {
        return array_values(array_filter(self::cases(), fn (self $m): bool => $m->isOffline()));
    }
}
