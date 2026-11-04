<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Disbursement;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A disbursement has just been paid and (for money) debited from its fund (FR-DSB-03).
 * Fired only after the transaction commits. Listeners come later (project spend, impact updates).
 */
class DisbursementPaid implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Disbursement $disbursement) {}
}
