<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Donation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A donation has just been confirmed and credited (BR-03).
 * Fired only after the database transaction commits, so listeners (receipt PDF,
 * email, WhatsApp — later weeks) never see money that was rolled back.
 */
class DonationPaid implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Donation $donation) {}
}
