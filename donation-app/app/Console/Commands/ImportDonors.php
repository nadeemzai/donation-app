<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Imports\DonorImportService;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Go-live (Week 12 Day 5): bring in the NGO's existing donors from a CSV.
 *
 *   php artisan donors:import /home/deploy/donors.csv            ← dry run: checks, writes nothing
 *   php artisan donors:import /home/deploy/donors.csv --commit   ← imports
 *
 * Each run writes a report (line, result, name, donor code, reason — no contact details) to
 * storage/app/private/imports/. Delete the source CSV from the server afterwards: it holds
 * phone numbers and CNICs.
 */
class ImportDonors extends Command
{
    protected $signature = 'donors:import {file : Path to the CSV file} {--commit : Really import (without it: a dry run)}';

    protected $description = 'Import existing donors from a CSV (dry run unless --commit)';

    public function handle(DonorImportService $importer): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file) || ! is_readable($file)) {
            $this->error("Cannot read {$file}.");

            return self::FAILURE;
        }

        $commit = (bool) $this->option('commit');

        try {
            $report = $importer->import((string) file_get_contents($file), dryRun: ! $commit);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $path = 'imports/donor-import-'.now()->format('Y-m-d-His').($commit ? '' : '-dry-run').'.csv';
        Storage::disk('local')->put($path, $importer->reportCsv($report));

        $this->table(['', 'Donors'], [
            [$commit ? 'Created' : 'Would be created', $report['created']],
            ['Skipped (already there / repeated)', $report['skipped']],
            ['Errors (fix in the file)', $report['errors']],
        ]);

        foreach (array_slice(array_filter($report['rows'], fn (array $r): bool => $r['status'] !== 'created' && $r['status'] !== 'would be created'), 0, 15) as $row) {
            $this->line("  line {$row['line']}: {$row['status']} — {$row['reason']}");
        }

        $this->info("Full report: storage/app/private/{$path}");

        if (! $commit) {
            $this->comment('Dry run — nothing was saved. Fix the errors, then run again with --commit.');
        }

        return $report['errors'] > 0 && ! $commit ? self::FAILURE : self::SUCCESS;
    }
}
