<?php

declare(strict_types=1);

namespace App\Services\Imports;

use App\Enums\DonorType;
use App\Models\Donor;
use App\Models\User;
use App\Support\Cnic;
use App\Support\Phone;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One-time import of the NGO's existing donors (Week 12 Day 5) from a CSV (Excel → Save as CSV).
 *
 * - Columns are found by header name (see COLUMNS), in any order; a UTF-8 BOM is fine.
 * - Every row is checked first; a dry run writes nothing and returns the same report.
 * - A donor is never imported twice: a row whose phone, e-mail or CNIC already belongs to a
 *   donor in the system (or to an earlier row of the file) is skipped and reported, so the
 *   import can be run again after fixing the file.
 * - WhatsApp receipts stay OFF unless the file says the donor agreed (consent).
 * - Only donors; past donations stay in the old records (their totals are not money we hold).
 */
class DonorImportService
{
    /** Accepted header names, compared in lower case without spaces, dashes or underscores. */
    public const COLUMNS = [
        'name' => ['name', 'fullname', 'donorname', 'donor'],
        'phone' => ['phone', 'mobile', 'mobileno', 'mobilenumber', 'cell', 'cellno', 'contact', 'contactno', 'phoneno', 'whatsappnumber'],
        'email' => ['email', 'emailaddress', 'mail'],
        'cnic' => ['cnic', 'cnicno', 'nic', 'cnicnumber'],
        'ntn' => ['ntn', 'ntnno'],
        'type' => ['type', 'donortype', 'category'],
        'city' => ['city', 'town'],
        'whatsapp' => ['whatsapp', 'whatsappoptin', 'receiptsonwhatsapp', 'whatsappok'],
        'language' => ['language', 'lang'],
    ];

    public const MAX_ROWS = 20000;

    /**
     * @return array{created: int, skipped: int, errors: int, rows: list<array{line: int, status: string, name: string, donor_code: string|null, reason: string}>}
     */
    public function import(string $contents, bool $dryRun = true, ?User $by = null): array
    {
        $rows = $this->parse($contents);
        $report = ['created' => 0, 'skipped' => 0, 'errors' => 0, 'rows' => []];
        $seen = ['phone' => [], 'email' => [], 'cnic' => []];

        foreach ($rows as $line => $row) {
            $name = trim((string) preg_replace('/\s+/u', ' ', $row['name'] ?? ''));

            try {
                $donor = $this->clean($row);
            } catch (DomainException $e) {
                $report['errors']++;
                $report['rows'][] = ['line' => $line, 'status' => 'error', 'name' => $name, 'donor_code' => null, 'reason' => $e->getMessage()];

                continue;
            }

            $duplicate = $this->duplicateOf($donor, $seen);

            if ($duplicate !== null) {
                $report['skipped']++;
                $report['rows'][] = ['line' => $line, 'status' => 'skipped', 'name' => $name, 'donor_code' => $duplicate['code'], 'reason' => $duplicate['reason']];

                continue;
            }

            foreach (['phone', 'email'] as $key) {
                if ($donor[$key] !== null) {
                    $seen[$key][$donor[$key]] = $line;
                }
            }
            if ($donor['cnic'] !== null) {
                $seen['cnic'][Cnic::normalize($donor['cnic'])] = $line;
            }

            $code = null;

            if (! $dryRun) {
                try {
                    $code = DB::transaction(fn (): ?string => Donor::query()->create($donor)->donor_code);
                } catch (Throwable $e) {
                    $report['errors']++;
                    $report['rows'][] = ['line' => $line, 'status' => 'error', 'name' => $name, 'donor_code' => null, 'reason' => 'Could not be saved: '.mb_substr($e->getMessage(), 0, 150)];

                    continue;
                }
            }

            $report['created']++;
            $report['rows'][] = ['line' => $line, 'status' => $dryRun ? 'would be created' : 'created', 'name' => $name, 'donor_code' => $code, 'reason' => ''];
        }

        if (! $dryRun) {
            activity('audit')->causedBy($by)->event('donors_imported')
                ->withProperties(['created' => $report['created'], 'skipped' => $report['skipped'], 'errors' => $report['errors']])
                ->log("Donor import: {$report['created']} created, {$report['skipped']} skipped, {$report['errors']} with errors");
        }

        return $report;
    }

    /**
     * The report as CSV (line, status, name, donor code, reason) — no phone, e-mail or CNIC.
     *
     * @param  array{rows: list<array{line: int, status: string, name: string, donor_code: string|null, reason: string}>}  $report
     */
    public function reportCsv(array $report): string
    {
        $out = fopen('php://temp', 'r+');
        assert($out !== false);
        fputcsv($out, ['Line in file', 'Result', 'Name', 'Donor code', 'Reason'], ',', '"', '');

        foreach ($report['rows'] as $row) {
            fputcsv($out, [$row['line'], $row['status'], $row['name'], $row['donor_code'], $row['reason']], ',', '"', '');
        }

        rewind($out);
        $csv = stream_get_contents($out) ?: '';
        fclose($out);

        return $csv;
    }

    /**
     * @return array<int, array<string, string>> line number in the file => column => value
     */
    private function parse(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        $stream = fopen('php://temp', 'r+');
        assert($stream !== false);
        fwrite($stream, $contents);
        rewind($stream);

        $header = fgetcsv($stream, null, ',', '"', '');

        if (! is_array($header)) {
            throw new DomainException('The file is empty.');
        }

        $columns = [];
        foreach ($header as $i => $title) {
            $key = mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', (string) $title) ?? '');

            foreach (self::COLUMNS as $field => $names) {
                if (! isset($columns[$field]) && in_array($key, $names, true)) {
                    $columns[$field] = $i;
                }
            }
        }

        if (! isset($columns['name']) || (! isset($columns['phone']) && ! isset($columns['email']))) {
            throw new DomainException('The file needs a "Name" column and a "Phone" or "Email" column in its first row. Use docs/go-live/donor-import-template.csv.');
        }

        $rows = [];
        $line = 1;

        while (($cells = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $line++;

            if (implode('', array_map(fn (mixed $c): string => trim((string) $c), $cells)) === '') {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                throw new DomainException('More than '.self::MAX_ROWS.' donors: split the file.');
            }

            $row = [];
            foreach ($columns as $field => $i) {
                $row[$field] = trim((string) ($cells[$i] ?? ''));
            }
            $rows[$line] = $row;
        }

        fclose($stream);

        if ($rows === []) {
            throw new DomainException('The file has a header but no donors.');
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     * @return array{name: string, phone: string|null, email: string|null, cnic: string|null, ntn: string|null, type: DonorType, city: string|null, preferences: array{language: string, whatsapp_opt_in: bool}}
     */
    private function clean(array $row): array
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $row['name'] ?? ''));

        if (mb_strlen($name) < 2) {
            throw new DomainException('Name missing.');
        }

        $rawPhone = $row['phone'] ?? '';
        // Excel drops the leading 0 of "03001234567".
        if (preg_match('/^3\d{9}$/', preg_replace('/\D/', '', $rawPhone) ?? '') === 1 && ! str_starts_with(trim($rawPhone), '+')) {
            $rawPhone = '0'.preg_replace('/\D/', '', $rawPhone);
        }

        $phone = Phone::normalize($rawPhone);
        if ($rawPhone !== '' && $phone === null) {
            throw new DomainException("Phone \"{$rawPhone}\" is not a mobile number (03XX-XXXXXXX, or +country code from abroad).");
        }

        $email = ($row['email'] ?? '') === '' ? null : mb_strtolower((string) $row['email']);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException("E-mail \"{$email}\" is not valid.");
        }

        if ($phone === null && $email === null) {
            throw new DomainException('Needs a mobile number or an e-mail address.');
        }

        $cnic = null;
        if (($row['cnic'] ?? '') !== '') {
            $digits = Cnic::normalize((string) $row['cnic']);

            if (strlen($digits) !== 13) {
                throw new DomainException('CNIC must have 13 digits.');
            }

            $cnic = substr($digits, 0, 5).'-'.substr($digits, 5, 7).'-'.substr($digits, 12);
        }

        $type = in_array(mb_strtolower($row['type'] ?? ''), ['corporate', 'company', 'organisation', 'organization', 'business'], true)
            ? DonorType::Corporate
            : DonorType::Individual;

        return [
            'name' => mb_substr($name, 0, 255),
            'phone' => $phone,
            'email' => $email,
            'cnic' => $cnic,
            'ntn' => ($row['ntn'] ?? '') !== '' ? mb_substr((string) $row['ntn'], 0, 20) : null,
            'type' => $type,
            'city' => ($row['city'] ?? '') !== '' ? mb_substr((string) $row['city'], 0, 100) : null,
            'preferences' => [
                'language' => in_array(mb_strtolower($row['language'] ?? ''), ['ur', 'urdu', 'اردو'], true) ? 'ur' : 'en',
                'whatsapp_opt_in' => in_array(mb_strtolower($row['whatsapp'] ?? ''), ['yes', 'y', 'true', '1', 'haan', 'ہاں'], true),
            ],
        ];
    }

    /**
     * @param  array{phone: string|null, email: string|null, cnic: string|null}  $donor
     * @param  array{phone: array<string, int>, email: array<string, int>, cnic: array<string, int>}  $seen
     * @return array{code: string|null, reason: string}|null
     */
    private function duplicateOf(array $donor, array $seen): ?array
    {
        foreach (['phone' => 'phone', 'email' => 'e-mail'] as $key => $label) {
            if ($donor[$key] === null) {
                continue;
            }

            if (isset($seen[$key][$donor[$key]])) {
                return ['code' => null, 'reason' => "Same {$label} as line {$seen[$key][$donor[$key]]} of this file."];
            }

            $existing = Donor::withTrashed()->where($key, $donor[$key])->first();
            if ($existing !== null) {
                return ['code' => $existing->donor_code, 'reason' => "A donor with this {$label} is already in the system."];
            }
        }

        if ($donor['cnic'] !== null) {
            $digits = Cnic::normalize($donor['cnic']);

            if (isset($seen['cnic'][$digits])) {
                return ['code' => null, 'reason' => "Same CNIC as line {$seen['cnic'][$digits]} of this file."];
            }

            $existing = Donor::withTrashed()->where('cnic_hash', Cnic::hash($digits))->first();
            if ($existing !== null) {
                return ['code' => $existing->donor_code, 'reason' => 'A donor with this CNIC is already in the system (different phone / e-mail) — check and merge by hand.'];
            }
        }

        return null;
    }
}
