<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Donation lifecycle (FR-DON-02, BR-03, BR-05).
 *
 *   pending ──► paid ──► partially_refunded ──► refunded
 *      │          └──────────────────────────────► refunded
 *      ├──► failed
 *      └──► cancelled
 *
 * Only "paid" money is counted. Refunds never edit a donation's amount — they
 * add a reversal and move the status.
 */
enum DonationStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::PartiallyRefunded => 'Partly refunded',
            self::Refunded => 'Refunded',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Failed, self::Cancelled => 'gray',
            self::PartiallyRefunded, self::Refunded => 'danger',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Pending => [self::Paid, self::Failed, self::Cancelled],
            self::Paid => [self::PartiallyRefunded, self::Refunded],
            self::PartiallyRefunded => [self::PartiallyRefunded, self::Refunded],
            self::Failed, self::Cancelled, self::Refunded => [],
        }, true);
    }

    /** Money was received at some point; the donation's core fields are frozen (BR-05). */
    public function isSettled(): bool
    {
        return in_array($this, [self::Paid, self::PartiallyRefunded, self::Refunded], true);
    }

    /** Finished without money: no further change possible. */
    public function isClosedWithoutPayment(): bool
    {
        return in_array($this, [self::Failed, self::Cancelled], true);
    }
}
