<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DonationPaid;
use App\Services\Messaging\DonationMessages;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Every confirmed donation — gateway, proof approval or staff entry — gets its receipt and
 * a thank-you on the donor's channels (FR-RCP-01, Week 5 definition of done).
 * Queued: the PDF and messages never slow down a payment callback.
 */
class SendReceiptAndThanks implements ShouldQueue
{
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public int $timeout = 150; // headless Chrome for the PDF

    public function __construct(private readonly DonationMessages $messages) {}

    public function handle(DonationPaid $event): void
    {
        $this->messages->thankYou($event->donation->fresh() ?? $event->donation);
    }
}
