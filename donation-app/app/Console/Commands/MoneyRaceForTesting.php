<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DonationChannel;
use App\Enums\FundType;
use App\Enums\PaymentMethod;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Services\DonationService;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Test helper for the MySQL money concurrency tests (tests/Feature/Donations/
 * MoneyConcurrencyTest.php). Several of these run at the same time against the
 * same fund; the test then checks nothing was lost or posted twice.
 *
 *   setup   --count=40 [--paid]   create one fund, one donor and N donations; prints JSON ids
 *   confirm --ids=1,2,3           confirm each donation; prints "ok <id>" / "refused <id>"
 *   refund  --ids=7 --amount=X    refund each donation by X; prints "ok <id>" / "refused <id>"
 *   cleanup                       remove everything the race created
 *
 * Refuses to run outside APP_ENV=testing — setup and cleanup write and delete data directly.
 */
class MoneyRaceForTesting extends Command
{
    protected $signature = 'money:race-test {step : setup|confirm|refund|cleanup} {--count=1} {--paid} {--amount=125.50} {--ids=}';

    protected $description = 'Testing only: run one side of a money concurrency race';

    protected $hidden = true;

    /** Tables the race writes to, children first. */
    private const TABLES = ['activity_log', 'fund_ledger_entries', 'donation_refunds', 'donations', 'donors', 'funds', 'number_sequences'];

    public function handle(DonationService $donations): int
    {
        if (! app()->environment('testing')) {
            $this->error('This command only runs with APP_ENV=testing.');

            return self::FAILURE;
        }

        return match ((string) $this->argument('step')) {
            'setup' => $this->setup($donations),
            'confirm' => $this->each(fn (Donation $d) => $donations->confirm($d)),
            'refund' => $this->each(fn (Donation $d) => $donations->refund($d, (string) $this->option('amount'), 'Concurrency test', PaymentMethod::BankTransfer)),
            'cleanup' => $this->cleanup(),
            default => self::FAILURE,
        };
    }

    private function setup(DonationService $donations): int
    {
        $fund = Fund::query()->create(['code' => 'RACE', 'name' => 'Race Fund', 'type' => FundType::General, 'is_active' => true]);
        $donor = Donor::query()->create(['name' => 'Race Donor', 'email' => 'race@example.test']);
        $ids = [];

        for ($i = 0; $i < max(1, (int) $this->option('count')); $i++) {
            $donation = $donations->create([
                'donor_id' => $donor->id,
                'fund_id' => $fund->id,
                'amount' => (string) $this->option('amount'),
                'channel' => DonationChannel::Web,
                'payment_method' => PaymentMethod::JazzCash,
            ]);

            if ((bool) $this->option('paid')) {
                $donations->confirm($donation);
            }

            $ids[] = $donation->id;
        }

        $this->line((string) json_encode(['fund_id' => $fund->id, 'donation_ids' => $ids]));

        return self::SUCCESS;
    }

    /**
     * @param  callable(Donation): mixed  $step
     */
    private function each(callable $step): int
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $this->option('ids'))));

        foreach ($ids as $id) {
            try {
                $step(Donation::query()->findOrFail($id));
                $this->line("ok {$id}");
            } catch (DomainException $e) {
                $this->line("refused {$id}");
            }
        }

        return self::SUCCESS;
    }

    private function cleanup(): int
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (self::TABLES as $table) {
            DB::table($table)->delete();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        return self::SUCCESS;
    }
}
