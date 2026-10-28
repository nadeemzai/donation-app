<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\DonationRefund;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A refund has been recorded and debited. Fired after commit, like DonationPaid.
 * Later weeks: notify the donor, void or reissue the receipt.
 */
class DonationRefunded implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public DonationRefund $refund) {}
}
