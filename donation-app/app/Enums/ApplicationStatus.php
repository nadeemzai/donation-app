<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * FR-BEN-02: submitted → verifying → approved / rejected. A rejected application can be
 * looked at again (back to verifying) when new facts come in; approved is final — further
 * help is a new application.
 */
enum ApplicationStatus: string implements HasColor, HasLabel
{
    case Submitted = 'submitted';
    case Verifying = 'verifying';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Verifying => 'Being verified',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Submitted => 'gray',
            self::Verifying => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Submitted => [self::Verifying, self::Rejected],
            self::Verifying => [self::Approved, self::Rejected],
            self::Rejected => [self::Verifying],
            self::Approved => [],
        }, true);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Submitted, self::Verifying], true);
    }
}
