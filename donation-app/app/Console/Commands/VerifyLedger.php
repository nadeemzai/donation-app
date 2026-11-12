<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LedgerAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * FR-FIN-01: checks that every fund balance, donation, refund and campaign total
 * agrees with the ledger. Runs nightly (routes/console.php). Exit code 1 and an
 * error in the log when anything disagrees, so monitoring can alert on it.
 * Never changes data.
 */
class VerifyLedger extends Command
{
    protected $signature = 'ledger:verify';

    protected $description = 'Check that fund balances, donations, refunds and campaign totals match the ledger';

    public function handle(LedgerAuditService $audit): int
    {
        $problems = $audit->problems();

        if ($problems === []) {
            $this->info('Ledger OK: every balance and total matches.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        $this->newLine();
        $this->error(count($problems).' problem(s) found. Do not correct balances by hand — investigate, then post reversals.');

        Log::error('ledger:verify found problems', ['problems' => $problems]);

        return self::FAILURE;
    }
}
