<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * One attempt to pay a donation through a gateway (FR-PAY-01/02).
 *
 *   initiated ──► pending ──► succeeded
 *       │            ├──────► failed
 *       │            ├──────► expired      (no answer in time — Week 4 Day 5)
 *       └────────────┴──────► needs_review (amount mismatch, paid twice, late success…)
 *
 * failed / expired can still move to needs_review if the gateway later says the money
 * was taken — that money must never be lost silently.
 */
enum PaymentStatus: string implements HasColor, HasLabel
{
    case Initiated = 'initiated';
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Expired = 'expired';
    case NeedsReview = 'needs_review';

    public function label(): string
    {
        return match ($this) {
            self::Initiated => 'Started',
            self::Pending => 'Waiting for gateway',
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
            self::Expired => 'Expired',
            self::NeedsReview => 'Needs review',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Initiated, self::Pending => 'warning',
            self::Succeeded => 'success',
            self::Failed, self::Expired => 'gray',
            self::NeedsReview => 'danger',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Initiated => [self::Pending, self::Succeeded, self::Failed, self::Expired, self::NeedsReview],
            self::Pending => [self::Succeeded, self::Failed, self::Expired, self::NeedsReview],
            self::Failed, self::Expired => [self::NeedsReview],
            self::Succeeded, self::NeedsReview => [],
        }, true);
    }

    /** Still waiting for the gateway's answer. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Initiated, self::Pending], true);
    }
}
