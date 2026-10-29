<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Donation;
use App\Models\Donor;
use App\Services\TaxCertificateService;
use App\Support\FiscalYear;
use DomainException;
use Illuminate\Console\Command;

/**
 * FR-RCP-03: certificates for a fiscal year from the command line.
 *
 *   php artisan tax-certificates:generate            last completed year (queued)
 *   php artisan tax-certificates:generate 2025 --now FY 2025-26, right here
 *
 * Generates only — sending to donors is Finance's decision in the admin.
 */
class GenerateTaxCertificates extends Command
{
    protected $signature = 'tax-certificates:generate {year? : Year the fiscal year starts in, e.g. 2025 for FY 2025-26} {--now : Generate in this process instead of queueing}';

    protected $description = 'Generate annual tax certificates for every eligible donor';

    public function handle(TaxCertificateService $certificates): int
    {
        $year = $this->argument('year') !== null ? new FiscalYear((int) $this->argument('year')) : FiscalYear::lastCompleted();

        if (! (bool) $this->option('now')) {
            $result = $certificates->generateForYear($year);
            $this->info("FY {$year->label()}: {$result['queued']} certificate(s) queued, ".count($result['skipped']).' donor(s) skipped.');
            $this->reportSkipped($result['skipped']);

            return self::SUCCESS;
        }

        $issued = 0;
        $skipped = [];
        $tz = (string) config('app.timezone');
        $donorIds = Donation::query()->counted()
            ->whereBetween('paid_at', [$year->start()->setTimezone($tz), $year->end()->setTimezone($tz)])
            ->distinct()->pluck('donor_id');

        foreach (Donor::withTrashed()->whereIn('id', $donorIds)->orderBy('name')->get() as $donor) {
            try {
                $certificate = $certificates->generate($donor, $year);
                $this->line("{$certificate->certificate_no}  {$donor->name}");
                $issued++;
            } catch (DomainException $e) {
                $skipped[$donor->id] = $e->getMessage();
            }
        }

        $this->info("FY {$year->label()}: {$issued} certificate(s) ready, ".count($skipped).' donor(s) skipped.');
        $this->reportSkipped($skipped);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $skipped
     */
    private function reportSkipped(array $skipped): void
    {
        foreach ($skipped as $reason) {
            $this->warn("  skipped — {$reason}");
        }
    }
}
