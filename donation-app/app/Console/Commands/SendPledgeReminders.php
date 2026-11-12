<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PledgeReminderService;
use Illuminate\Console\Command;

/** Every morning: open due pledge periods and remind donors, with a pay link (Week 8 Day 2). */
class SendPledgeReminders extends Command
{
    protected $signature = 'pledges:remind';

    protected $description = 'Send pledge reminders for today, and second reminders for periods still unpaid';

    public function handle(PledgeReminderService $reminders): int
    {
        $stats = $reminders->run();

        $this->info(sprintf(
            'Pledges due: %d · reminders sent: %d · second reminders: %d · paused (archived donor / closed fund): %d · failed: %d',
            $stats['opened'], $stats['reminded'], $stats['second_reminders'], $stats['paused'], $stats['failed'],
        ));

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
