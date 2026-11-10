<?php

declare(strict_types=1);

namespace App\Reports;

use App\Settings\GeneralSettings;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\LaravelPdf\Facades\Pdf;
use Throwable;

/**
 * Excel and PDF copies of a report (FR-RPT-02). Excel keeps numbers as numbers (money with two
 * decimals) so Finance can total and pivot them; the PDF is A4 landscape for printing / sharing.
 * Uses OpenSpout (comes with Filament) and spatie/laravel-pdf (already used for receipts).
 */
final class ReportExporter
{
    /** Writes the .xlsx to a temporary file and returns its path. */
    public static function excel(ReportTable $report): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'report-');
        $bold = (new Style)->setFontBold();

        $columnStyles = [];
        foreach ($report->columns as $i => $column) {
            $columnStyles[$i] = match ($column['type']) {
                'money' => (new Style)->setFormat('#,##0.00'),
                'percent' => (new Style)->setFormat('0.0"%"'),
                'int' => (new Style)->setFormat('#,##0'),
                default => new Style,
            };
        }

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName(mb_substr($report->key, 0, 31));

        $writer->addRow(Row::fromValues([self::orgName().' — '.$report->title], (new Style)->setFontBold()->setFontSize(13)));
        $writer->addRow(Row::fromValues(['Period: '.$report->period.' · Generated '.now()->format('d M Y H:i')]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(array_map(fn (array $c): string => $c['label'], $report->columns), $bold));

        foreach ($report->rows as $row) {
            $writer->addRow(Row::fromValuesWithStyles(self::cells($report, $row), null, $columnStyles));
        }

        if ($report->totals !== null) {
            $writer->addRow(Row::fromValuesWithStyles(self::cells($report, $report->totals), (new Style)->setFontBold(), $columnStyles));
        }

        if ($report->notes !== []) {
            $writer->addRow(Row::fromValues([]));
            foreach ($report->notes as $note) {
                $writer->addRow(Row::fromValues([$note]));
            }
        }

        $writer->close();

        return $path;
    }

    /** The PDF as bytes. */
    public static function pdf(ReportTable $report): string
    {
        return Pdf::view('pdf.report', ['report' => $report, 'org' => self::orgName(), 'generatedAt' => now()->format('d M Y H:i')])
            ->format('a4')
            ->landscape()
            ->margins(10, 10, 10, 10)
            ->generatePdfContent();
    }

    /**
     * Numbers as numbers (Excel can add them up); text and blanks as they are.
     *
     * @param  list<string|int|null>  $row
     * @return list<string|int|float|null>
     */
    private static function cells(ReportTable $report, array $row): array
    {
        $cells = [];

        foreach ($row as $i => $value) {
            $cells[] = match (true) {
                $value === null || $value === '' => null,
                $report->isNumeric($i) && is_numeric($value) => ($report->columns[$i]['type'] === 'int' ? (int) $value : (float) $value),
                default => (string) $value,
            };
        }

        return $cells;
    }

    private static function orgName(): string
    {
        try {
            return app(GeneralSettings::class)->org_name;
        } catch (Throwable) {
            return (string) config('app.name');
        }
    }
}
