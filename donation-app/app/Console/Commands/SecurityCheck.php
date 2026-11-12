<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\SecurityChecks;
use Illuminate\Console\Command;

/**
 * Go-live / after-deploy security check (Week 12 Day 1). Exit code 1 when anything must be
 * fixed, so a deploy script can stop on it. Expect failures on a developer machine.
 */
class SecurityCheck extends Command
{
    protected $signature = 'security:check';

    protected $description = 'Check production security settings (debug, HTTPS cookies, 2FA, queues, live gateways…)';

    public function handle(): int
    {
        $checks = SecurityChecks::run();
        $marks = [SecurityChecks::PASS => '<info>PASS</info>', SecurityChecks::WARN => '<comment>WARN</comment>', SecurityChecks::FAIL => '<error>FAIL</error>'];

        $this->table(['', 'Check', 'Details'], array_map(fn (array $c): array => [$marks[$c['status']], $c['check'], $c['detail']], $checks));

        $failed = SecurityChecks::failed($checks);

        if ($failed > 0) {
            $this->error("{$failed} check(s) failed — fix them before real donors use the system.");

            return self::FAILURE;
        }

        $this->info('All required checks passed. Review any warnings.');

        return self::SUCCESS;
    }
}
