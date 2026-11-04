<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DonationPaid;
use App\Services\QurbaniService;

/** A paid donation for a Qurbani booking confirms the booking (Week 11). */
class ConfirmQurbaniBooking
{
    public function __construct(private readonly QurbaniService $qurbani) {}

    public function handle(DonationPaid $event): void
    {
        $this->qurbani->confirm($event->donation);
    }
}
