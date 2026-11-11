<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Application;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\Disbursement;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\InventoryItem;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// W12-D2 — N+1 guard. Each busy page is loaded with a few rows and then with many; the number
// of database queries must not grow with the rows. A failure names the page: look for a
// relation used per row that is missing from with() / the resource's getEloquentQuery().

/**
 * Queries one GET runs.
 */
function queriesFor(string $url): int
{
    Cache::flush();
    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->get($url)->assertOk();

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

/**
 * @param  Closure(int): void  $seed  adds that many rows
 * @return array{0: int, 1: int} queries with 2 rows, then with 9 rows (fits one table page)
 */
function queriesAtTwoSizes(string $url, Closure $seed): array
{
    $seed(2);
    queriesFor($url); // warm up: settings, permissions, first-time lookups
    $few = queriesFor($url);

    $seed(7);
    $many = queriesFor($url);

    return [$few, $many];
}

dataset('public pages', [
    'home' => ['/', fn (int $n) => Campaign::factory()->live()->count($n)->create(['is_featured' => true])],
    'campaigns' => ['/campaigns', fn (int $n) => Campaign::factory()->live()->count($n)->create()],
    'ways to give' => ['/ways-to-give', fn (int $n) => Fund::factory()->count($n)->create()],
    'api campaigns' => ['/api/v1/campaigns', fn (int $n) => Campaign::factory()->live()->count($n)->create()],
    'api funds' => ['/api/v1/funds', fn (int $n) => Fund::factory()->count($n)->create()],
]);

dataset('admin lists', [
    'donations' => ['/admin/donations', fn (int $n) => Donation::factory()->count($n)->create()],
    'donors' => ['/admin/donors', fn (int $n) => Donor::factory()->count($n)->create()],
    'payments' => ['/admin/payments', fn (int $n) => Payment::factory()->count($n)->create()],
    'campaigns' => ['/admin/campaigns', fn (int $n) => Campaign::factory()->live()->count($n)->create()],
    'funds' => ['/admin/funds', fn (int $n) => Fund::factory()->count($n)->create()],
    'beneficiaries' => ['/admin/beneficiaries', fn (int $n) => Beneficiary::factory()->count($n)->create()],
    'applications' => ['/admin/applications', fn (int $n) => Application::factory()->count($n)->create()],
    'disbursements' => ['/admin/disbursements', fn (int $n) => Disbursement::factory()->count($n)->create()],
    'stock items' => ['/admin/inventory-items', fn (int $n) => InventoryItem::factory()->stocked('10')->count($n)->create()],
]);

it('does not run more queries for more rows on a public page', function (string $url, Closure $seed) {
    [$few, $many] = queriesAtTwoSizes($url, $seed);

    expect($many)->toBeLessThanOrEqual($few, "{$url}: {$few} queries with 2 rows, {$many} with 9");
})->with('public pages');

it('does not run more queries for more rows on an admin list', function (string $url, Closure $seed) {
    $this->actingAs(staffUser(Role::SuperAdmin));

    [$few, $many] = queriesAtTwoSizes($url, $seed);

    expect($many)->toBeLessThanOrEqual($few, "{$url}: {$few} queries with 2 rows, {$many} with 9");
})->with('admin lists');
