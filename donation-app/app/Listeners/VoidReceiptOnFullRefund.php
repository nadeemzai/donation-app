<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\DonationStatus;
use App\Events\DonationRefunded;
use App\Services\ReceiptService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * A fully refunded donation's receipt is voided (its PDF says VOID), so it can't be used
 * for tax. A partly refunded donation keeps its receipt (FR-RCP-01, BR-05).
 */
class VoidReceiptOnFullRefund implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly ReceiptService $receipts) {}

    public function handle(DonationRefunded $event): void
    {
        $donation = $event->refund->donation()->with('receipt')->first();

        if ($donation?->status === DonationStatus::Refunded && $donation->receipt !== null) {
            $this->receipts->void($donation->receipt, 'Donation refunded in full');
        }
    }
}
