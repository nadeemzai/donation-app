<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What happened to one gateway callback (FR-PAY-02).
 */
enum WebhookOutcome: string implements HasColor, HasLabel
{
    case Received = 'received';     // stored, not processed yet
    case Processed = 'processed';   // changed a payment
    case Duplicate = 'duplicate';   // payment already final — nothing done (replay)
    case Rejected = 'rejected';     // bad signature or unknown payment
    case Error = 'error';           // crashed while processing — see error column

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Received => 'gray',
            self::Processed => 'success',
            self::Duplicate => 'info',
            self::Rejected, self::Error => 'danger',
        };
    }
}
