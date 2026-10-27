<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Campaign lifecycle: draft → live → closed (FR-CMP-02, BR-09).
 * A closed campaign is final; it can't be reopened.
 */
enum CampaignStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Live = 'live';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Live => 'Live',
            self::Closed => 'Closed',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Live => 'success',
            self::Closed => 'danger',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => in_array($next, [self::Live, self::Closed], true),
            self::Live => $next === self::Closed,
            self::Closed => false,
        };
    }

    /** Only live campaigns accept donations. */
    public function acceptsDonations(): bool
    {
        return $this === self::Live;
    }
}
