<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Pakistan's fiscal (tax) year: 1 July – 30 June, Pakistan time.
 * Identified by the year it STARTS in: FiscalYear(2025) = FY 2025-26 = 1 Jul 2025 – 30 Jun 2026.
 */
final readonly class FiscalYear
{
    public function __construct(public int $startYear)
    {
        if ($startYear < 2000 || $startYear > 2100) {
            throw new InvalidArgumentException("Unlikely fiscal year [{$startYear}].");
        }
    }

    public static function containing(DateTimeInterface $date): self
    {
        $local = CarbonImmutable::instance($date)->setTimezone('Asia/Karachi');

        return new self($local->month >= 7 ? $local->year : $local->year - 1);
    }

    public static function current(): self
    {
        return self::containing(CarbonImmutable::now());
    }

    /** The year whose certificates are normally due now: the one that just ended. */
    public static function lastCompleted(): self
    {
        return new self(self::current()->startYear - 1);
    }

    public function start(): CarbonImmutable
    {
        return CarbonImmutable::create($this->startYear, 7, 1, 0, 0, 0, 'Asia/Karachi');
    }

    public function end(): CarbonImmutable
    {
        return CarbonImmutable::create($this->startYear + 1, 6, 30, 23, 59, 59, 'Asia/Karachi');
    }

    /** "2025-26" */
    public function label(): string
    {
        return $this->startYear.'-'.substr((string) ($this->startYear + 1), 2);
    }

    /**
     * Recent years for a picker, newest first: [2025 => 'FY 2025-26', …].
     *
     * @return array<int, string>
     */
    public static function options(int $count = 6): array
    {
        $options = [];
        $current = self::current()->startYear;

        for ($year = $current; $year > $current - $count; $year--) {
            $options[$year] = 'FY '.(new self($year))->label();
        }

        return $options;
    }
}
