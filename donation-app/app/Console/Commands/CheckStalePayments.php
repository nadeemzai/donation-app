<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Jobs\CheckPaymentStatus;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Console\Command;

/**
 * FR-PAY-02: finds gateway payments still open after PaymentService::EXPIRES_AFTER_MINUTES
 * and asks the gateway about each (queued job, one per payment). Payments still undecided
 * after GIVE_UP_AFTER_MINUTES expire. Runs every 5 minutes — see routes/console.php.
 *
 *   php artisan payments:check-stale          queue the checks
 *   php artisan payments:check-stale --now    check right here (no queue worker needed)
 */
class CheckStalePayments extends Command
{
    protected $signature = 'payments:check-stale {--now : Check in this process instead of queueing} {--limit=200 : Most payments per run}';

    protected $description = 'Ask the gateway about payments still pending, and expire the ones it never decided';

    public function handle(PaymentService $payments): int
    {
        $stale = Payment::query()
            ->whereIn('status', [PaymentStatus::Initiated->value, PaymentStatus::Pending->value])
            ->where('expires_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('last_checked_at')
                ->orWhere('last_checked_at', '<=', now()->subMinutes(PaymentService::RECHECK_AFTER_MINUTES)))
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->pluck('id');

        foreach ($stale as $id) {
            if ((bool) $this->option('now')) {
                $payment = Payment::query()->find($id);

                if ($payment !== null) {
                    $after = $payments->checkStatus($payment);
                    $this->line("{$after->reference}: {$after->status->label()}");
                }
            } else {
                CheckPaymentStatus::dispatch((int) $id);
            }
        }

        $this->info($stale->count().' stale payment(s) '.((bool) $this->option('now') ? 'checked.' : 'queued for checking.'));

        return self::SUCCESS;
    }
}
