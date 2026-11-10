<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** How one settlement line compares with our payments (FR-FIN-02). */
enum SettlementMatch: string implements HasColor, HasLabel
{
    case Matched = 'matched';
    case AmountMismatch = 'amount_mismatch';
    case NotFound = 'not_found';
    case NotSucceeded = 'not_succeeded';
    case WrongGateway = 'wrong_gateway';
    case Duplicate = 'duplicate';
    case Unreadable = 'unreadable';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::Matched => 'Matched',
            self::AmountMismatch => 'Amount differs',
            self::NotFound => 'No such payment',
            self::NotSucceeded => 'Not paid in our system',
            self::WrongGateway => 'Other gateway',
            self::Duplicate => 'Settled twice',
            self::Unreadable => 'Unreadable row',
            self::Ignored => 'Not settled (ignored)',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Matched => 'success',
            self::Ignored => 'gray',
            self::NotSucceeded, self::Unreadable => 'warning',
            default => 'danger',
        };
    }

    /** Needs Finance to look at it. */
    public function isProblem(): bool
    {
        return ! in_array($this, [self::Matched, self::Ignored], true);
    }

    /** @return list<string> */
    public static function problemValues(): array
    {
        return array_values(array_map(fn (self $s): string => $s->value, array_filter(self::cases(), fn (self $s): bool => $s->isProblem())));
    }
}
