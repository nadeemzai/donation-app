<?php

declare(strict_types=1);

use App\Support\FiscalYear;
use Carbon\CarbonImmutable;

// FR-RCP-03 — Pakistan fiscal year: 1 July – 30 June

it('finds the fiscal year for a date, in Pakistan time', function (string $at, string $tz, int $startYear) {
    expect(FiscalYear::containing(CarbonImmutable::parse($at, $tz))->startYear)->toBe($startYear);
})->with([
    'last second of June' => ['2026-06-30 23:59:59', 'Asia/Karachi', 2025],
    'first second of July' => ['2026-07-01 00:00:00', 'Asia/Karachi', 2026],
    'January' => ['2026-01-15 12:00:00', 'Asia/Karachi', 2025],
    'UTC evening of 30 June is already 1 July in Karachi' => ['2026-06-30 20:30:00', 'UTC', 2026],
]);

it('knows its start, end and label', function () {
    $year = new FiscalYear(2025);

    expect($year->label())->toBe('2025-26')
        ->and($year->start()->format('Y-m-d H:i:s T'))->toBe('2025-07-01 00:00:00 PKT')
        ->and($year->end()->format('Y-m-d H:i:s'))->toBe('2026-06-30 23:59:59')
        ->and((new FiscalYear(2029))->label())->toBe('2029-30');
});

it('refuses nonsense years', function () {
    new FiscalYear(1999);
})->throws(InvalidArgumentException::class);
