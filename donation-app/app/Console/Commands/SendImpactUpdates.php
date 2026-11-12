<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ImpactUpdateService;
use Illuminate\Console\Command;

/** Every few minutes: message donors about project updates whose publish time has come (FR-PRJ-02). */
class SendImpactUpdates extends Command
{
    protected $signature = 'impact:notify';

    protected $description = 'Send published project impact updates to the donors linked to each project';

    public function handle(ImpactUpdateService $updates): int
    {
        $this->info('Donors messaged: '.$updates->notifyDue());

        return self::SUCCESS;
    }
}
