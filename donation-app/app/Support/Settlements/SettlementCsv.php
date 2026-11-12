<?php

declare(strict_types=1);

namespace App\Support\Settlements;

use App\Enums\PaymentMethod;
use DomainException;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Reads a gateway settlement CSV into plain rows (FR-FIN-02).
 *
 * - Columns are found by header name (config/settlements.php), so the order does not matter and
 *   a few title lines above the header are skipped.
 * - Comma, semicolon or tab separated; a UTF-8 BOM (Excel) is fine.
 * - Amounts like "Rs 1,250.00" are read as 1250.00; a row that cannot be read is kept and
 *   flagged rather than dropped, so nothing in the file is lost.
 */
final class SettlementCsv
{
    private const HEADER_SCAN = 10;

    private const DATE_FORMATS = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'YmdHis', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'd-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y', 'd M Y', 'd-M-Y', 'd M Y H:i'];

    /**
     * @return list<array{line_no: int, our_reference: ?string, gateway_ref: ?string, amount: ?string, fee: ?string, net: ?string, transacted_at: ?Carbon, gateway_status: ?string, raw: array<string, string>, error: ?string}>
     */
    public static function parse(string $contents, PaymentMethod $gateway): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        if (trim($contents) === '') {
            throw new DomainException('The file is empty.');
        }

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        $rows = self::rows($contents);
        [$headerIndex, $columns] = self::findHeader($rows);
        $headers = $rows[$headerIndex];
        $paisa = (bool) config('settlements.amounts_in_paisa.'.$gateway->value, false);
        $max = (int) config('settlements.max_rows', 20000);
        $parsed = [];

        foreach (array_slice($rows, $headerIndex + 1, null, true) as $index => $cells) {
            if (self::isBlank($cells)) {
                continue;
            }

            if (count($parsed) >= $max) {
                throw new DomainException("The file has more than {$max} rows. Split it by day.");
            }

            $raw = [];
            foreach ($headers as $i => $name) {
                $label = trim($name) !== '' ? trim($name) : 'Column '.($i + 1);
                $raw[$label] = trim($cells[$i] ?? '');
            }

            $cell = fn (string $key): ?string => isset($columns[$key]) && trim($cells[$columns[$key]] ?? '') !== '' ? trim($cells[$columns[$key]]) : null;

            $error = null;
            $amount = self::money($cell('amount'), $paisa, $error);
            $fee = self::money($cell('fee'), $paisa, $error);
            $net = self::money($cell('net'), $paisa, $error);

            // Only net (and maybe a fee) in the file: work out what the donor paid.
            if ($amount === null && $net !== null) {
                $amount = $fee !== null ? bcadd($net, $fee, 2) : $net;
            }

            if ($net === null && $amount !== null && $fee !== null) {
                $net = bcsub($amount, $fee, 2);
            }

            $reference = $cell('reference');
            $gatewayRef = $cell('gateway_ref');

            if ($reference === null && $gatewayRef === null) {
                $error ??= 'No payment reference or gateway transaction id on this row.';
            }

            if ($amount === null) {
                $error ??= 'No amount on this row.';
            }

            $parsed[] = [
                'line_no' => $index + 1,
                'our_reference' => $reference !== null ? mb_substr($reference, 0, 100) : null,
                'gateway_ref' => $gatewayRef !== null ? mb_substr($gatewayRef, 0, 100) : null,
                'amount' => $amount,
                'fee' => $fee,
                'net' => $net,
                'transacted_at' => self::date($cell('date')),
                'gateway_status' => $cell('status') !== null ? mb_substr((string) $cell('status'), 0, 50) : null,
                'raw' => $raw,
                'error' => $error,
            ];
        }

        if ($parsed === []) {
            throw new DomainException('The file has a header but no rows.');
        }

        return $parsed;
    }

    /** Whether a row's status means the money was settled (rows without a status column count as settled). */
    public static function isSettled(?string $status): bool
    {
        if ($status === null) {
            return true;
        }

        /** @var list<string> $settled */
        $settled = config('settlements.settled_statuses', []);

        return in_array(mb_strtolower(trim($status)), $settled, true);
    }

    public static function normaliseHeader(string $header): string
    {
        return mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $header) ?? '');
    }

    /**
     * @return list<list<string>>
     */
    private static function rows(string $contents): array
    {
        $delimiter = ',';

        // Pick the delimiter from the busiest of the first lines (title lines may have none).
        $lines = array_slice(preg_split('/\r\n|\r|\n/', $contents) ?: [], 0, self::HEADER_SCAN);
        $best = 0;
        foreach ([',', ';', "\t"] as $candidate) {
            $count = max(array_map(fn (string $l): int => substr_count($l, $candidate), $lines ?: ['']));
            if ($count > $best) {
                $best = $count;
                $delimiter = $candidate;
            }
        }

        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new DomainException('The file could not be read.');
        }

        fwrite($stream, $contents);
        rewind($stream);

        $rows = [];
        while (($cells = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $rows[] = array_map(fn (mixed $c): string => is_string($c) ? $c : '', $cells);
        }

        fclose($stream);

        return $rows;
    }

    /**
     * @param  list<list<string>>  $rows
     * @return array{0: int, 1: array<string, int>}
     */
    private static function findHeader(array $rows): array
    {
        /** @var array<string, list<string>> $aliases */
        $aliases = config('settlements.columns', []);

        foreach (array_slice($rows, 0, self::HEADER_SCAN, true) as $index => $cells) {
            $columns = [];

            foreach ($cells as $i => $cell) {
                $name = self::normaliseHeader($cell);

                foreach ($aliases as $key => $names) {
                    if (! isset($columns[$key]) && in_array($name, $names, true)) {
                        $columns[$key] = $i;
                    }
                }
            }

            $hasId = isset($columns['reference']) || isset($columns['gateway_ref']);
            $hasMoney = isset($columns['amount']) || isset($columns['net']);

            if ($hasId && $hasMoney) {
                return [$index, $columns];
            }
        }

        $seen = implode(', ', array_filter(array_map('trim', $rows[0] ?? [])));

        throw new DomainException('Could not find the columns. The file needs a header with a payment reference (or transaction id) and an amount. '
            .'Headers found: '.($seen !== '' ? $seen : 'none').'. New header names can be added in config/settlements.php.');
    }

    /**
     * @param  list<string>  $cells
     */
    private static function isBlank(array $cells): bool
    {
        return implode('', array_map('trim', $cells)) === '';
    }

    private static function money(?string $value, bool $paisa, ?string &$error): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/(?i)\b(rs|pkr)\.?|[\s,]/', '', $value) ?? '';

        if (preg_match('/^\d{1,12}(\.\d{1,2})?$/', $clean) !== 1) {
            $error ??= "Amount \"{$value}\" could not be read.";

            return null;
        }

        return $paisa ? bcdiv($clean, '100', 2) : bcadd($clean, '0', 2);
    }

    private static function date(?string $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        foreach (self::DATE_FORMATS as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }

            $errors = Carbon::getLastErrors();

            if ($date instanceof Carbon && ($errors === false || $errors['warning_count'] === 0) && $date->year >= 2000 && $date->year <= 2100) {
                return $date;
            }
        }

        return null;
    }
}
