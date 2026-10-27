<?php

declare(strict_types=1);

use App\Enums\SequenceKey;
use App\Models\Donor;
use App\Services\NumberSequenceService;
use App\Settings\NumberingSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// BR-04 — sequential, unique, never reused public numbers

function numbers(): NumberSequenceService
{
    return app(NumberSequenceService::class);
}

it('issues numbers in the PREFIX-YEAR-000001 format', function () {
    $year = now()->year;

    expect(numbers()->next(SequenceKey::Donation))->toBe("DN-{$year}-000001")
        ->and(numbers()->next(SequenceKey::Donation))->toBe("DN-{$year}-000002")
        ->and(numbers()->next(SequenceKey::Receipt))->toBe("RC-{$year}-000001")
        ->and(numbers()->next(SequenceKey::Disbursement))->toBe("DS-{$year}-000001");
});

it('keeps each document type on its own counter', function () {
    numbers()->next(SequenceKey::Donation);
    numbers()->next(SequenceKey::Donation);
    numbers()->next(SequenceKey::Receipt);

    expect(DB::table('number_sequences')->where('key', 'donation')->value('last_number'))->toEqual(2)
        ->and(DB::table('number_sequences')->where('key', 'receipt')->value('last_number'))->toEqual(1);
});

it('restarts at 000001 in a new year and keeps last year\'s counter', function () {
    $this->travelTo(Carbon::create(2026, 12, 31, 23, 59, 0, 'Asia/Karachi'));
    numbers()->next(SequenceKey::Donation);
    expect(numbers()->next(SequenceKey::Donation))->toBe('DN-2026-000002');

    $this->travelTo(Carbon::create(2027, 1, 1, 0, 1, 0, 'Asia/Karachi'));
    expect(numbers()->next(SequenceKey::Donation))->toBe('DN-2027-000001')
        ->and(DB::table('number_sequences')->where(['key' => 'donation', 'year' => 2026])->value('last_number'))->toEqual(2);
});

it('uses the Pakistan calendar year, not UTC', function () {
    // 1 Jan 00:30 in Karachi is still 31 Dec 19:30 UTC.
    $this->travelTo(Carbon::create(2027, 1, 1, 0, 30, 0, 'Asia/Karachi'));

    expect(numbers()->next(SequenceKey::Donation))->toStartWith('DN-2027-');
});

it('applies a changed prefix and length to new numbers only', function () {
    $year = now()->year;
    expect(numbers()->next(SequenceKey::Donation))->toBe("DN-{$year}-000001");

    $settings = app(NumberingSettings::class);
    $settings->donation_prefix = 'DON';
    $settings->pad_length = 4;
    $settings->save();

    expect(numbers()->next(SequenceKey::Donation))->toBe("DON-{$year}-0002");
});

it('re-issues a number whose transaction rolled back, so committed numbers never repeat or skip', function () {
    $year = now()->year;

    DB::beginTransaction();
    $rolledBack = numbers()->next(SequenceKey::Donation);
    DB::rollBack();

    expect($rolledBack)->toBe("DN-{$year}-000001")
        ->and(numbers()->next(SequenceKey::Donation))->toBe("DN-{$year}-000001")
        ->and(numbers()->next(SequenceKey::Donation))->toBe("DN-{$year}-000002");
});

it('issues 200 unique numbers in a row', function () {
    $issued = collect(range(1, 200))->map(fn () => numbers()->next(SequenceKey::Receipt));

    expect($issued->unique())->toHaveCount(200)
        ->and($issued->last())->toEndWith('-000200');
});

it('gives every new donor a donor code automatically', function () {
    $year = now()->year;

    $first = Donor::factory()->create();
    $second = Donor::factory()->guest()->create();

    expect($first->donor_code)->toBe("DR-{$year}-000001")
        ->and($second->donor_code)->toBe("DR-{$year}-000002");
});

it('refuses to run the testing command outside the testing environment', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('numbers:issue-test', ['key' => 'donation'])->assertFailed();

    expect(DB::table('number_sequences')->count())->toBe(0);
});
