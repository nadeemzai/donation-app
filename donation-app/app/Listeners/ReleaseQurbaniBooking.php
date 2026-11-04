<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DonationClosed;
use App\Services\QurbaniService;

/** A failed or cancelled donation frees the Qurbani shares it was paying for (Week 11). */
class ReleaseQurbaniBooking
{
    public function __construct(private readonly QurbaniService $qurbani) {}

    public function handle(DonationClosed $event): void
    {
        $this->qurbani->release($event->donation, $event->reason);
    }
}
