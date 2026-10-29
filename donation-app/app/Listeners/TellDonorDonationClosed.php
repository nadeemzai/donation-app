<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DonationClosed;
use App\Services\Messaging\DonationMessages;
use Illuminate\Contracts\Queue\ShouldQueue;

/** "Payment did not go through" / "proof rejected" messages (FR-NTF-01/02). Queued. */
class TellDonorDonationClosed implements ShouldQueue
{
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(private readonly DonationMessages $messages) {}

    public function handle(DonationClosed $event): void
    {
        $this->messages->closed($event->donation, $event->status, $event->reason);
    }
}
