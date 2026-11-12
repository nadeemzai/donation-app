<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * FR-DSB: requested → approved → paid, or rejected. The requester can withdraw (cancel) a
 * request until it is decided. Paid, rejected and cancelled are final.
 */
enum DisbursementStatus: string implements HasColor, HasLabel
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Waiting for approval',
            self::Approved => 'Approved — to pay',
            self::Paid => 'Paid',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Withdrawn',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Approved => 'info',
            self::Paid => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'gray',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Requested => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::Paid, self::Cancelled],
            self::Paid, self::Rejected, self::Cancelled => [],
        }, true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Paid, self::Rejected, self::Cancelled], true);
    }
}
