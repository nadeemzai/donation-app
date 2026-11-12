<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\OpsHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Every 10 minutes (routes/console.php): checks Horizon, the queue, failed jobs, stuck payments
 * and disk space. Problems go to the log and, at most once every 2 hours for the same problems,
 * to OPS_ALERT_EMAIL. Exit code 1 on problems, so the heartbeat is not pinged either.
 */
class OpsHealthCheck extends Command
{
    protected $signature = 'ops:health';

    protected $description = 'Check Horizon, queue, failed jobs, stuck payments and disk space; e-mail problems';

    public function handle(): int
    {
        $problems = OpsHealth::problems();

        if ($problems === []) {
            $this->info('All good.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        Log::error('ops:health found problems', ['problems' => $problems]);

        $to = config('donation.ops.alert_email');
        $key = 'ops-health-alert:'.md5(implode('|', $problems));

        if (is_string($to) && $to !== '' && Cache::add($key, true, now()->addHours(2))) {
            Mail::raw(
                'The donation system found these problems on '.config('app.url').":\n\n- ".implode("\n- ", $problems)
                ."\n\nThis e-mail repeats every 2 hours while the problems last.",
                fn ($message) => $message->to($to)->subject('['.config('app.name').'] Needs attention: '.count($problems).' problem(s)'),
            );
        }

        return self::FAILURE;
    }
}
