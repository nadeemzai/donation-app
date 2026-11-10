<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SettlementMatch;
use App\Models\Payment;
use App\Models\Settlement;
use App\Models\SettlementLine;
use App\Models\User;
use App\Support\Money;
use App\Support\Settlements\SettlementCsv;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Gateway reconciliation (Week 11 Day 4, FR-FIN-02).
 *
 *   import()   → read a JazzCash / Easypaisa settlement CSV, keep the file, match every row
 *   rematch()  → match the open rows again (after a payment was confirmed or reviewed)
 *   resolve()  → Finance explains a flagged row; it stays flagged in the history
 *   missingFrom() → our successful payments for that day that the gateway did not settle
 *
 * A row matches when it points to one of our payments (by our reference, else by the gateway's
 * transaction id) that is on the same gateway, succeeded here, has the same amount and was not
 * already settled on another row. Reconciliation never changes payments, donations or the ledger:
 * a flag is fixed where it belongs (e.g. a late success is reviewed on the payment) and then
 * re-matched. Gateway fees are recorded on the rows for reporting only.
 */
class SettlementService
{
    public function import(PaymentMethod $gateway, Carbon $date, string $contents, string $fileName, ?string $notes = null, ?User $by = null): Settlement
    {
        if (! $gateway->isGateway()) {
            throw new DomainException('Settlement files come from JazzCash or Easypaisa only.');
        }

        if ($date->copy()->startOfDay()->isFuture()) {
            throw new DomainException('The settlement date cannot be in the future.');
        }

        if (strlen($contents) > (int) config('settlements.max_kb', 5120) * 1024) {
            throw new DomainException('The file is too large. Split it by day.');
        }

        $hash = hash('sha256', $contents);
        $existing = Settlement::query()->where('file_hash', $hash)->first();

        if ($existing !== null) {
            throw new DomainException("This file was already imported ({$existing->label()}, #{$existing->id}).");
        }

        $rows = SettlementCsv::parse($contents, $gateway);
        $path = "settlements/{$gateway->value}/{$date->format('Y-m-d')}-{$hash}.csv";
        Storage::disk('local')->put($path, $contents);

        return DB::transaction(function () use ($gateway, $date, $fileName, $notes, $by, $rows, $hash, $path): Settlement {
            $settlement = Settlement::query()->create([
                'gateway' => $gateway,
                'settlement_date' => $date->toDateString(),
                'file_name' => mb_substr(basename($fileName), 0, 255),
                'file_path' => $path,
                'file_hash' => $hash,
                'row_count' => count($rows),
                'notes' => filled($notes) ? trim((string) $notes) : null,
                'imported_by' => $by?->id,
                'imported_at' => now(),
            ]);

            $gross = $fee = $net = '0.00';

            foreach ($rows as $row) {
                $settled = SettlementCsv::isSettled($row['gateway_status']);

                SettlementLine::query()->create([
                    'settlement_id' => $settlement->id,
                    'line_no' => $row['line_no'],
                    'our_reference' => $row['our_reference'],
                    'gateway_ref' => $row['gateway_ref'],
                    'amount' => $row['amount'],
                    'fee' => $row['fee'],
                    'net' => $row['net'],
                    'transacted_at' => $row['transacted_at'],
                    'gateway_status' => $row['gateway_status'],
                    'raw' => $row['raw'],
                    'match_status' => match (true) {
                        ! $settled => SettlementMatch::Ignored,
                        $row['error'] !== null => SettlementMatch::Unreadable,
                        default => SettlementMatch::NotFound, // matched just below
                    },
                    'mismatch_reason' => $settled ? $row['error'] : 'Gateway status "'.$row['gateway_status'].'" — not settled.',
                ]);

                if ($settled) {
                    $gross = bcadd($gross, $row['amount'] ?? '0', 2);
                    $fee = bcadd($fee, $row['fee'] ?? '0', 2);
                    $net = bcadd($net, $row['net'] ?? $row['amount'] ?? '0', 2);
                }
            }

            $settlement->forceFill(['gross_total' => $gross, 'fee_total' => $fee, 'net_total' => $net])->save();
            $this->matchOpenLines($settlement);

            activity('audit')->performedOn($settlement)->causedBy($by)->event('settlement_imported')
                ->withProperties(['rows' => $settlement->row_count, 'matched' => $settlement->matched_count, 'problems' => $settlement->problem_count])
                ->log("Settlement {$settlement->label()} imported: {$settlement->row_count} rows, {$settlement->matched_count} matched, {$settlement->problem_count} flagged");

            return $settlement;
        });
    }

    /** Match the flagged, unresolved rows again. */
    public function rematch(Settlement $settlement, ?User $by = null): Settlement
    {
        return DB::transaction(function () use ($settlement, $by): Settlement {
            $locked = Settlement::query()->lockForUpdate()->findOrFail($settlement->id);
            $before = $locked->problem_count;
            $this->matchOpenLines($locked);

            activity('audit')->performedOn($locked)->causedBy($by)->event('settlement_rematched')
                ->withProperties(['problems_before' => $before, 'problems_after' => $locked->problem_count])
                ->log("Settlement {$locked->label()} matched again: {$before} → {$locked->problem_count} flagged");

            return $locked;
        });
    }

    /** Finance explains a flagged row (e.g. "refunded at gateway", "test payment"). */
    public function resolve(SettlementLine $line, string $note, User $by): SettlementLine
    {
        $note = trim($note) !== '' ? trim($note) : throw new DomainException('Write how this row was sorted out.');

        return DB::transaction(function () use ($line, $note, $by): SettlementLine {
            $locked = SettlementLine::query()->lockForUpdate()->findOrFail($line->id);

            if (! $locked->isOpenProblem()) {
                throw new DomainException('Only flagged rows that are still open can be resolved.');
            }

            $locked->forceFill(['resolved_at' => now(), 'resolved_by' => $by->id, 'resolution_note' => mb_substr($note, 0, 500)])->save();
            $this->recount($locked->settlement);

            activity('audit')->performedOn($locked->settlement)->causedBy($by)->event('settlement_line_resolved')
                ->withProperties(['line_no' => $locked->line_no, 'status' => $locked->match_status->value, 'note' => $note])
                ->log("Settlement {$locked->settlement->label()} row {$locked->line_no} resolved: {$note}");

            return $locked;
        });
    }

    /**
     * Payments that succeeded here on the settlement day but are on no matched settlement row.
     *
     * @return Collection<int, Payment>
     */
    public function missingFrom(Settlement $settlement, int $limit = 500): Collection
    {
        return Payment::query()
            ->with('donation.donor')
            ->where('method', $settlement->gateway->value)
            ->where('status', PaymentStatus::Succeeded->value)
            ->whereDate('completed_at', $settlement->settlement_date->toDateString())
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('settlement_lines')
                ->whereColumn('settlement_lines.payment_id', 'payments.id')
                ->where('settlement_lines.match_status', SettlementMatch::Matched->value))
            ->orderBy('completed_at')
            ->limit($limit)
            ->get();
    }

    /** The mismatch report as CSV: open flagged rows, then payments missing from the file. */
    public function mismatchCsv(Settlement $settlement): string
    {
        $out = fopen('php://temp', 'r+');
        assert($out !== false);

        fputcsv($out, ['Settlement', $settlement->label(), 'File', $settlement->file_name], ',', '"', '');
        fputcsv($out, [], ',', '"', '');
        fputcsv($out, ['Row', 'Problem', 'Our reference', 'Gateway transaction id', 'Amount in file', 'Our amount', 'Details', 'Resolved', 'Resolution'], ',', '"', '');

        $lines = $settlement->lines()->with('payment')->whereIn('match_status', SettlementMatch::problemValues())->get();

        foreach ($lines as $line) {
            fputcsv($out, [
                $line->line_no,
                $line->match_status->label(),
                $line->our_reference,
                $line->gateway_ref,
                $line->amount,
                $line->payment?->amount,
                $line->mismatch_reason,
                $line->resolved_at?->format('Y-m-d H:i'),
                $line->resolution_note,
            ], ',', '"', '');
        }

        fputcsv($out, [], ',', '"', '');
        fputcsv($out, ['Paid in our system but not in this file'], ',', '"', '');
        fputcsv($out, ['Our reference', 'Gateway transaction id', 'Amount', 'Paid at', 'Donation'], ',', '"', '');

        foreach ($this->missingFrom($settlement, 5000) as $payment) {
            fputcsv($out, [$payment->reference, $payment->gateway_ref, $payment->amount, $payment->completed_at?->format('Y-m-d H:i'), $payment->donation->donation_no], ',', '"', '');
        }

        rewind($out);
        $csv = stream_get_contents($out) ?: '';
        fclose($out);

        return $csv;
    }

    /** Caller holds a transaction. Matches every row that is not matched, ignored or resolved. */
    private function matchOpenLines(Settlement $settlement): void
    {
        $lines = SettlementLine::query()
            ->where('settlement_id', $settlement->id)
            ->whereNull('resolved_at')
            ->whereNotIn('match_status', [SettlementMatch::Matched->value, SettlementMatch::Ignored->value, SettlementMatch::Unreadable->value])
            ->orderBy('line_no')
            ->lockForUpdate()
            ->get();

        foreach ($lines as $line) {
            [$status, $payment, $reason] = $this->compare($line, $settlement->gateway);

            $line->forceFill(['match_status' => $status, 'payment_id' => $payment?->id, 'mismatch_reason' => $reason])->save();
        }

        $settlement->forceFill(['last_matched_at' => now()])->save();
        $this->recount($settlement);
    }

    /**
     * @return array{0: SettlementMatch, 1: Payment|null, 2: string|null}
     */
    private function compare(SettlementLine $line, PaymentMethod $gateway): array
    {
        $payment = $this->findPayment($line);

        if ($payment === null) {
            return [SettlementMatch::NotFound, null, 'No payment with this reference or transaction id.'];
        }

        if ($payment->method !== $gateway) {
            return [SettlementMatch::WrongGateway, $payment, "Payment {$payment->reference} was made by {$payment->method->label()}."];
        }

        $other = SettlementLine::query()
            ->where('payment_id', $payment->id)
            ->where('match_status', SettlementMatch::Matched->value)
            ->whereKeyNot($line->id)
            ->with('settlement')
            ->first();

        if ($other !== null) {
            return [SettlementMatch::Duplicate, $payment, "Already settled on {$other->settlement->label()}, row {$other->line_no}."];
        }

        if ($payment->status !== PaymentStatus::Succeeded) {
            return [SettlementMatch::NotSucceeded, $payment, "The gateway settled it but our payment is {$payment->status->label()}. Check it on the payment, then match again."];
        }

        if (bccomp((string) $line->amount, (string) $payment->amount, 2) !== 0) {
            return [SettlementMatch::AmountMismatch, $payment, 'File says '.Money::rupees((string) $line->amount).', we recorded '.Money::rupees((string) $payment->amount).'.'];
        }

        return [SettlementMatch::Matched, $payment, null];
    }

    private function findPayment(SettlementLine $line): ?Payment
    {
        if ($line->our_reference !== null) {
            $payment = Payment::query()->where('reference', $line->our_reference)->first();

            if ($payment !== null) {
                return $payment;
            }
        }

        if ($line->gateway_ref !== null) {
            return Payment::query()->where('gateway_ref', $line->gateway_ref)->first();
        }

        return null;
    }

    private function recount(Settlement $settlement): void
    {
        $counts = DB::table('settlement_lines')
            ->where('settlement_id', $settlement->id)
            ->selectRaw('match_status, CASE WHEN resolved_at IS NULL THEN 1 ELSE 0 END as is_open, COUNT(*) as n')
            ->groupBy('match_status', 'is_open')
            ->get();

        $matched = $problems = $ignored = 0;

        foreach ($counts as $row) {
            $status = SettlementMatch::from((string) $row->match_status);
            $n = (int) $row->n;

            if ($status === SettlementMatch::Matched) {
                $matched += $n;
            } elseif ($status === SettlementMatch::Ignored) {
                $ignored += $n;
            } elseif ((int) $row->is_open === 1) {
                $problems += $n;
            }
        }

        $settlement->forceFill(['matched_count' => $matched, 'problem_count' => $problems, 'ignored_count' => $ignored])->save();
    }

    /**
     * Open flagged rows across all settlements (navigation badge).
     *
     * @return Builder<SettlementLine>
     */
    public static function openProblems(): Builder
    {
        return SettlementLine::query()->whereIn('match_status', SettlementMatch::problemValues())->whereNull('resolved_at');
    }
}
