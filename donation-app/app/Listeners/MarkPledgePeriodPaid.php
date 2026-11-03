<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DonationPaid;
use App\Services\PledgeReminderService;
use Illuminate\Contracts\Queue\ShouldQueue;

/** A donation from a pledge reminder's pay link fulfils that period (Week 8 Day 2). Queued. */
class MarkPledgePeriodPaid implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly PledgeReminderService $reminders) {}

    public function handle(DonationPaid $event): void
    {
        $this->reminders->markPaid($event->donation->fresh() ?? $event->donation);
    }
}
