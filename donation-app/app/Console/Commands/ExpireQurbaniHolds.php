<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\QurbaniService;
use Illuminate\Console\Command;

/** Cancels Qurbani bookings that were never paid (their hold ran out). */
class ExpireQurbaniHolds extends Command
{
    protected $signature = 'qurbani:expire';

    protected $description = 'Cancel unpaid Qurbani bookings whose hold has run out';

    public function handle(QurbaniService $qurbani): int
    {
        $this->info('Bookings cancelled: '.$qurbani->expireHolds());

        return self::SUCCESS;
    }
}
