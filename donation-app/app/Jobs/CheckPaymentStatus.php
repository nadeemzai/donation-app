<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Ask the gateway about one stale payment and settle it (FR-PAY-02, BR-03).
 * Unique per payment, so two runs of the scheduler never check the same payment at once.
 */
class CheckPaymentStatus implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** A gateway call can wait on the donor's phone; give it room. */
    public int $timeout = 120;

    public int $tries = 1; // the scheduler tries again in 5 minutes anyway

    public int $uniqueFor = 600;

    public function __construct(public int $paymentId) {}

    public function uniqueId(): string
    {
        return (string) $this->paymentId;
    }

    public function handle(PaymentService $payments): void
    {
        $payment = Payment::query()->find($this->paymentId);

        if ($payment !== null) {
            $payments->checkStatus($payment);
        }
    }
}
