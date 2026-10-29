<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Process\Pool;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

// BR-03, BR-07, FR-FIN-01 under real concurrency. Needs MySQL row locking, so it is
// skipped on SQLite (local default) and runs in GitHub Actions, where tests use MySQL 8.
//
// Separate PHP processes (artisan money:race-test) hit the same fund at the same
// moment. Their writes are committed for real, so this test reads and cleans up
// through its own "race" connection, outside the test transaction.

/**
 * @return array<string, string>
 */
function raceEnv(): array
{
    $mysql = config('database.connections.mysql');

    return [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => (string) $mysql['host'],
        'DB_PORT' => (string) $mysql['port'],
        'DB_DATABASE' => (string) $mysql['database'],
        'DB_USERNAME' => (string) $mysql['username'],
        'DB_PASSWORD' => (string) $mysql['password'],
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'null', // receipts/messages are not part of the race — don't run them
    ];
}

/**
 * @param  list<string>  $args
 */
function raceRun(array $args): ProcessResult
{
    $result = Process::path(base_path())->env(raceEnv())->timeout(120)->run([PHP_BINARY, 'artisan', ...$args]);

    expect($result->successful())->toBeTrue(implode(' ', $args).' failed: '.$result->errorOutput().$result->output());

    return $result;
}

/**
 * Start every command at once and wait for all of them.
 *
 * @param  list<list<string>>  $commands
 * @return Collection<int, string> combined output lines
 */
function racePool(array $commands): Collection
{
    $results = Process::pool(function (Pool $pool) use ($commands): void {
        foreach ($commands as $i => $args) {
            $pool->as("worker-{$i}")->path(base_path())->env(raceEnv())->timeout(120)
                ->command([PHP_BINARY, 'artisan', ...$args]);
        }
    })->start()->wait();

    $lines = collect();
    foreach ($results->collect() as $name => $result) {
        expect($result->successful())->toBeTrue("{$name} failed: ".$result->errorOutput());
        $lines = $lines->merge(array_values(array_filter(explode("\n", trim($result->output())))));
    }

    return $lines;
}

/**
 * @param  list<string>  $extra
 * @return array{fund_id: int, donation_ids: list<int>}
 */
function raceSetup(array $extra): array
{
    /** @var array{fund_id: int, donation_ids: list<int>} $ids */
    $ids = json_decode(trim(raceRun(['money:race-test', 'setup', ...$extra])->output()), true, flags: JSON_THROW_ON_ERROR);

    return $ids;
}

function raceDb(): Connection
{
    config(['database.connections.race' => config('database.connections.mysql')]);

    return DB::connection('race');
}

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Needs MySQL row locking — runs in CI');
    }

    raceRun(['money:race-test', 'cleanup']);
});

afterEach(function () {
    if (DB::getDriverName() === 'mysql') {
        raceRun(['money:race-test', 'cleanup']);
        DB::purge('race');
    }
});

it('credits the fund exactly when 4 processes confirm 40 donations at once', function () {
    ['fund_id' => $fundId, 'donation_ids' => $ids] = raceSetup(['--count=40', '--amount=125.50']);

    // Interleave the ids so all four workers post to the fund throughout the race.
    $chunks = collect($ids)->groupBy(fn (int $id, int $i): int => $i % 4);
    $lines = racePool($chunks->map(fn (Collection $c): array => ['money:race-test', 'confirm', '--ids='.$c->implode(',')])->values()->all());

    expect($lines->filter(fn (string $l): bool => str_starts_with($l, 'ok ')))->toHaveCount(40);

    $db = raceDb();
    expect((string) $db->table('funds')->where('id', $fundId)->value('balance'))->toBe('5020.00') // 40 × 125.50
        ->and($db->table('fund_ledger_entries')->where('fund_id', $fundId)->count())->toBe(40)
        ->and($db->table('donations')->where('status', 'paid')->count())->toBe(40);

    // Every running balance follows on from the one before — no lost update.
    $running = '0.00';
    foreach ($db->table('fund_ledger_entries')->where('fund_id', $fundId)->orderBy('id')->get() as $line) {
        $running = bcadd($running, (string) $line->amount, 2);
        expect((string) $line->balance_after)->toBe($running);
    }

    raceRun(['ledger:verify']);
});

it('posts a donation only once when 4 processes confirm it at the same moment', function () {
    ['fund_id' => $fundId, 'donation_ids' => [$id]] = raceSetup(['--count=1', '--amount=2500.00']);

    racePool(array_fill(0, 4, ['money:race-test', 'confirm', "--ids={$id}"]));

    $db = raceDb();
    expect($db->table('fund_ledger_entries')->where('source_id', $id)->count())->toBe(1)
        ->and((string) $db->table('funds')->where('id', $fundId)->value('balance'))->toBe('2500.00');
});

it('refunds only once when 4 processes refund the full amount at once (BR-07)', function () {
    ['fund_id' => $fundId, 'donation_ids' => [$id]] = raceSetup(['--count=1', '--amount=1000.00', '--paid']);

    $lines = racePool(array_fill(0, 4, ['money:race-test', 'refund', "--ids={$id}", '--amount=1000.00']));

    expect($lines->filter(fn (string $l): bool => $l === "ok {$id}"))->toHaveCount(1)
        ->and($lines->filter(fn (string $l): bool => $l === "refused {$id}"))->toHaveCount(3);

    $db = raceDb();
    expect($db->table('donation_refunds')->count())->toBe(1)
        ->and((string) $db->table('funds')->where('id', $fundId)->value('balance'))->toBe('0.00')
        ->and($db->table('donations')->where('id', $id)->value('status'))->toBe('refunded');

    raceRun(['ledger:verify']);
});
