<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** What the field officer recommends after a verification visit (FR-BEN-03). */
enum VisitRecommendation: string implements HasColor, HasLabel
{
    case Approve = 'approve';
    case Reject = 'reject';
    case NeedsMoreInfo = 'needs_more_info';

    public function label(): string
    {
        return match ($this) {
            self::Approve => 'Approve',
            self::Reject => 'Reject',
            self::NeedsMoreInfo => 'Needs more information',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Approve => 'success',
            self::Reject => 'danger',
            self::NeedsMoreInfo => 'warning',
        };
    }
}
