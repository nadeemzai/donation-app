<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SequenceKey;
use App\Services\NumberSequenceService;
use Illuminate\Console\Command;

/**
 * Test helper for the MySQL concurrency test: several of these run at once and
 * the test checks no number was issued twice. Refuses to run outside testing,
 * because issuing numbers for real would leave gaps.
 */
class IssueNumbersForTesting extends Command
{
    protected $signature = 'numbers:issue-test {key : donation|receipt|disbursement|donor} {--count=1}';

    protected $description = 'Testing only: issue document numbers and print them, one per line';

    protected $hidden = true;

    public function handle(NumberSequenceService $numbers): int
    {
        if (! app()->environment('testing')) {
            $this->error('This command only runs with APP_ENV=testing.');

            return self::FAILURE;
        }

        $key = SequenceKey::tryFrom((string) $this->argument('key'));

        if ($key === null) {
            $this->error('Unknown key.');

            return self::FAILURE;
        }

        for ($i = 0; $i < max(1, (int) $this->option('count')); $i++) {
            $this->line($numbers->next($key));
        }

        return self::SUCCESS;
    }
}
