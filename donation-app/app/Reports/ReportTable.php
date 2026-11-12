<?php

declare(strict_types=1);

namespace App\Reports;

use App\Support\Money;

/**
 * One finished report, ready for the screen, Excel or PDF (FR-RPT-02).
 *
 * Column types: text · int · money (decimal string, rupees) · percent (decimal string, 0–100).
 * Money stays a decimal string end to end — never a float.
 */
final readonly class ReportTable
{
    /**
     * @param  list<array{label: string, type: string}>  $columns
     * @param  list<list<string|int|null>>  $rows
     * @param  list<string|int|null>|null  $totals
     * @param  list<string>  $notes
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $period,
        public array $columns,
        public array $rows,
        public ?array $totals = null,
        public array $notes = [],
    ) {}

    /** How a cell reads on screen and in the PDF. */
    public function display(int $column, string|int|null $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($this->columns[$column]['type'] ?? 'text') {
            'money' => Money::rupees((string) $value),
            'int' => number_format((int) $value),
            'percent' => self::percent((string) $value),
            default => (string) $value,
        };
    }

    public function isNumeric(int $column): bool
    {
        return in_array($this->columns[$column]['type'] ?? 'text', ['int', 'money', 'percent'], true);
    }

    public function fileName(string $extension): string
    {
        return $this->key.'-'.now()->format('Y-m-d-His').'.'.$extension;
    }

    /** "12.50" → "12.5%", "100.0" → "100%". */
    public static function percent(string $value): string
    {
        $value = bcadd($value, '0', 1);

        return (str_ends_with($value, '.0') ? substr($value, 0, -2) : $value).'%';
    }
}
