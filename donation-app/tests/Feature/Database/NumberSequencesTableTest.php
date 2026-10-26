<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// BR-04 — storage for sequential public numbers

it('has the number_sequences table with the expected columns', function () {
    expect(Schema::hasTable('number_sequences'))->toBeTrue()
        ->and(Schema::hasColumns('number_sequences', [
            'id', 'key', 'prefix', 'year', 'last_number', 'created_at', 'updated_at',
        ]))->toBeTrue();
});

it('defaults last_number to zero', function () {
    DB::table('number_sequences')->insert(['key' => 'donation', 'prefix' => 'DN', 'year' => 2026]);

    expect(DB::table('number_sequences')->value('last_number'))->toEqual(0);
});

it('allows only one row per key per year', function () {
    DB::table('number_sequences')->insert(['key' => 'donation', 'prefix' => 'DN', 'year' => 2026]);
    DB::table('number_sequences')->insert(['key' => 'donation', 'prefix' => 'DN', 'year' => 2026]);
})->throws(QueryException::class);

it('allows the same key in a different year and different keys in the same year', function () {
    DB::table('number_sequences')->insert([
        ['key' => 'donation', 'prefix' => 'DN', 'year' => 2026],
        ['key' => 'donation', 'prefix' => 'DN', 'year' => 2027],
        ['key' => 'receipt', 'prefix' => 'RC', 'year' => 2026],
    ]);

    expect(DB::table('number_sequences')->count())->toBe(3);
});
