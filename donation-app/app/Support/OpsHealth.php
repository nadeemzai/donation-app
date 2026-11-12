<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Throwable;

/**
 * What can go wrong on the live server without anyone noticing (Week 12 Day 4).
 * Checked every 10 minutes by `ops:health`; problems are e-mailed to OPS_ALERT_EMAIL.
 * Read-only.
 */
final class OpsHealth
{
    public const QUEUE_BACKLOG = 500;

    public const STUCK_PAYMENT_HOURS = 2;

    public const MIN_FREE_DISK = 0.10;

    /**
     * @return list<string> problems, in plain words; empty when all is well
     */
    public static function problems(): array
    {
        $problems = [];

        // Background jobs: receipts, WhatsApp, e-mails, tax certificates.
        if (config('queue.default') === 'redis') {
            try {
                if (app(MasterSupervisorRepository::class)->all() === []) {
                    $problems[] = 'Horizon is not running: receipts and messages are not being sent. On the server: sudo supervisorctl start horizon';
                }
            } catch (Throwable $e) {
                $problems[] = 'Could not check Horizon / Redis: '.$e->getMessage();
            }
        }

        try {
            $waiting = Queue::size();
            if ($waiting > self::QUEUE_BACKLOG) {
                $problems[] = "{$waiting} jobs are waiting in the queue (receipts / messages are falling behind).";
            }
        } catch (Throwable $e) {
            $problems[] = 'Could not read the queue: '.$e->getMessage();
        }

        $failed = DB::table((string) config('queue.failed.table', 'failed_jobs'))->where('failed_at', '>=', now()->subHour())->count();
        if ($failed > 0) {
            $problems[] = "{$failed} background job(s) failed in the last hour — see /horizon → Failed jobs.";
        }

        // Payments: the 5-minute status check should settle every unfinished payment within an hour.
        $stuck = Payment::query()
            ->whereIn('status', [PaymentStatus::Initiated->value, PaymentStatus::Pending->value])
            ->where('initiated_at', '<', now()->subHours(self::STUCK_PAYMENT_HOURS))
            ->count();
        if ($stuck > 0) {
            $problems[] = "{$stuck} gateway payment(s) still open after ".self::STUCK_PAYMENT_HOURS.' hours — is the scheduler running and can the server reach the gateway?';
        }

        $review = Payment::query()->where('status', PaymentStatus::NeedsReview->value)->count();
        if ($review > 0) {
            $problems[] = "{$review} payment(s) need review by Finance (paid at the gateway after we had given up) — Donations → Payments.";
        }

        $total = @disk_total_space(base_path());
        $free = @disk_free_space(base_path());
        if (is_float($total) && is_float($free) && $total > 0 && $free / $total < self::MIN_FREE_DISK) {
            $problems[] = sprintf('Disk almost full: %.0f%% free. Old logs and local backups can be removed.', $free / $total * 100);
        }

        return $problems;
    }
}
