<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\DonationStatus;
use App\Models\Donation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A donation ended without money: failed (gateway) or cancelled (staff, rejected proof).
 * Fired after commit. Listeners tell the donor when it is useful to them.
 */
class DonationClosed implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Donation $donation,
        public DonationStatus $status,
        public ?string $reason = null,
    ) {}
}
