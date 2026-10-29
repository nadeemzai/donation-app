<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Donation;
use App\Services\ReceiptService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Issues (or re-renders) the receipt for a paid donation in the background (FR-RCP-01).
 * Day 4 dispatches it when a donation is paid. Safe to run twice: ReceiptService::issue()
 * returns the existing receipt and only renders a missing PDF.
 */
class IssueReceipt implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 120; // headless Chrome

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $uniqueFor = 600;

    public function __construct(public int $donationId) {}

    public function uniqueId(): string
    {
        return (string) $this->donationId;
    }

    public function handle(ReceiptService $receipts): void
    {
        $donation = Donation::query()->find($this->donationId);

        if ($donation !== null) {
            $receipts->issue($donation);
        }
    }
}
