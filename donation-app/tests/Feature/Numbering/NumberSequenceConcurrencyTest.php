<?php

declare(strict_types=1);

use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

// BR-04 under real concurrency. Needs MySQL row locking, so it is skipped on
// SQLite (local default) and runs in GitHub Actions, where tests use MySQL 8.
//
// Five separate PHP processes each issue 20 receipt numbers at the same time.
// If locking were broken, two processes would read the same last_number and
// hand out the same receipt number.

it('never issues the same number twice when 5 processes run at once', function () {
    $mysql = config('database.connections.mysql');

    $env = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => (string) $mysql['host'],
        'DB_PORT' => (string) $mysql['port'],
        'DB_DATABASE' => (string) $mysql['database'],
        'DB_USERNAME' => (string) $mysql['username'],
        'DB_PASSWORD' => (string) $mysql['password'],
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
    ];

    $results = Process::pool(function (Pool $pool) use ($env): void {
        foreach (range(1, 5) as $i) {
            $pool->as("worker-{$i}")
                ->path(base_path())
                ->env($env)
                ->timeout(120)
                ->command([PHP_BINARY, 'artisan', 'numbers:issue-test', 'receipt', '--count=20']);
        }
    })->start()->wait();

    $numbers = collect();
    foreach ($results->collect() as $name => $result) {
        expect($result->successful())->toBeTrue("{$name} failed: ".$result->errorOutput());
        $numbers = $numbers->merge(array_filter(explode("\n", trim($result->output()))));
    }

    // Clean up rows the workers committed (outside this test's transaction).
    config(['database.connections.cleanup' => $mysql]);
    DB::connection('cleanup')->table('number_sequences')->where('key', 'receipt')->delete();
    DB::purge('cleanup');

    expect($numbers)->toHaveCount(100)
        ->and($numbers->unique())->toHaveCount(100)
        ->and($numbers->sort()->values()->last())->toEndWith('-000100');
})->skip(fn () => DB::getDriverName() !== 'mysql', 'Needs MySQL row locking — runs in CI');
