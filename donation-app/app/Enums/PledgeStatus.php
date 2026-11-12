<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A pledge's life (Week 8): active ⇄ paused → cancelled; active → completed when its end
 * date passes. Cancelled and completed are final.
 */
enum PledgeStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Paused => 'warning',
            self::Cancelled => 'gray',
            self::Completed => 'info',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Active => [self::Paused, self::Cancelled, self::Completed],
            self::Paused => [self::Active, self::Cancelled],
            self::Cancelled, self::Completed => [],
        }, true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Cancelled, self::Completed], true);
    }
}
