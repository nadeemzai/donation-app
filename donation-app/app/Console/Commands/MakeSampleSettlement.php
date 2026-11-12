<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Staff testing (Week 12 Day 3): writes a JazzCash-style settlement CSV for one day from the
 * staging payments, with two deliberate problems (a wrong amount and an unknown transaction)
 * so testers see matching AND flags. Never runs in production.
 *
 *   php artisan settlements:sample 2027-01-20
 */
class MakeSampleSettlement extends Command
{
    protected $signature = 'settlements:sample {date? : The day of the payments (default today)} {--gateway=jazzcash}';

    protected $description = 'Staging only: a sample settlement CSV from that day\'s payments, with two problems in it';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('settlements:sample never runs in production — use the real file from the gateway.');

            return self::FAILURE;
        }

        $gateway = PaymentMethod::tryFrom((string) $this->option('gateway'));

        if ($gateway === null || ! $gateway->isGateway()) {
            $this->error('Choose --gateway=jazzcash or --gateway=easypaisa.');

            return self::FAILURE;
        }

        $date = Carbon::parse((string) ($this->argument('date') ?? today()->toDateString()));
        $payments = Payment::query()
            ->where('method', $gateway->value)
            ->where('status', PaymentStatus::Succeeded->value)
            ->whereDate('completed_at', $date->toDateString())
            ->orderBy('completed_at')
            ->get();

        $lines = ['Txn Ref No,Transaction ID,Amount,Fee,Net Amount,Status,Transaction Date'];

        foreach ($payments as $i => $payment) {
            // The second payment is settled 10 rupees short, to show "Amount differs".
            $amount = $i === 1 ? bcsub((string) $payment->amount, '10', 2) : (string) $payment->amount;
            $fee = bcmul($amount, '0.015', 2);
            $lines[] = implode(',', [$payment->reference, $payment->gateway_ref ?? '', $amount, $fee, bcsub($amount, $fee, 2), 'Success', $payment->completed_at?->format('Y-m-d H:i:s')]);
        }

        // A transaction that is not ours, to show "No such payment".
        $lines[] = implode(',', ['T-NOT-OURS-0001', 'GW-UNKNOWN-1', '2500.00', '37.50', '2462.50', 'Success', $date->format('Y-m-d').' 12:00:00']);

        $path = "uat/settlement-{$gateway->value}-{$date->format('Y-m-d')}.csv";
        Storage::disk('local')->put($path, implode("\n", $lines)."\n");

        $this->info(count($payments)." payment(s) + 1 unknown row written to storage/app/private/{$path}");

        if ($payments->count() < 2) {
            $this->warn('Fewer than 2 payments that day: make a couple of test donations first to see an amount mismatch.');
        }

        return self::SUCCESS;
    }
}
