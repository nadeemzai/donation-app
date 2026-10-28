<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Exceptions\InsufficientFundBalance;
use App\Models\Fund;
use App\Models\FundLedgerEntry;
use App\Models\User;
use App\Support\Money;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only place a fund balance changes (FR-FIN-01, CLAUDE.md rules 1–3, BR-07).
 *
 * - Every change is one append-only ledger line with the running balance after it.
 * - The fund row is locked (SELECT … FOR UPDATE) for the whole posting, so two
 *   postings to the same fund run one after the other and the balance never drifts.
 * - Each source record (a donation, a refund, a disbursement) posts at most once.
 * - A debit can never take a fund below zero.
 *
 * Lock order used everywhere: donation → fund → campaign. Keep it that way to avoid deadlocks.
 */
class FundLedgerService
{
    public function credit(Fund|int $fund, string $amount, Model $source, string $description, ?User $by = null): FundLedgerEntry
    {
        return $this->post(LedgerEntryType::Credit, $fund, $amount, $source, $description, $by);
    }

    /**
     * @throws InsufficientFundBalance when the fund doesn't hold enough (BR-07)
     */
    public function debit(Fund|int $fund, string $amount, Model $source, string $description, ?User $by = null): FundLedgerEntry
    {
        return $this->post(LedgerEntryType::Debit, $fund, $amount, $source, $description, $by);
    }

    /** Balance recalculated from the ledger lines alone. */
    public function balanceFromLedger(Fund|int $fund): string
    {
        $total = '0.00';

        FundLedgerEntry::query()
            ->where('fund_id', $fund instanceof Fund ? $fund->getKey() : $fund)
            ->orderBy('id')
            ->lazy(500)
            ->each(function (FundLedgerEntry $entry) use (&$total): void {
                $total = bcadd($total, $entry->signedAmount(), 2);
            });

        return $total;
    }

    /**
     * True when the stored balance, the last running balance and the sum of all lines agree.
     */
    public function isBalanced(Fund|int $fund): bool
    {
        $fundId = $fund instanceof Fund ? (int) $fund->getKey() : $fund;
        $stored = (string) Fund::withTrashed()->whereKey($fundId)->value('balance');
        $fromLines = $this->balanceFromLedger($fundId);
        $lastRunning = (string) (FundLedgerEntry::query()->where('fund_id', $fundId)->orderByDesc('id')->value('balance_after') ?? '0.00');

        return bccomp($stored, $fromLines, 2) === 0 && bccomp($lastRunning, $fromLines, 2) === 0;
    }

    private function post(LedgerEntryType $type, Fund|int $fund, string $amount, Model $source, string $description, ?User $by): FundLedgerEntry
    {
        $amount = $this->normalise($amount);

        if (! $source->exists) {
            throw new InvalidArgumentException('A ledger entry needs a saved source record.');
        }

        $fundId = $fund instanceof Fund ? (int) $fund->getKey() : $fund;

        return DB::transaction(function () use ($type, $fundId, $amount, $source, $description, $by): FundLedgerEntry {
            $locked = Fund::withTrashed()->lockForUpdate()->findOrFail($fundId);

            $alreadyPosted = FundLedgerEntry::query()
                ->where('source_type', $source->getMorphClass())
                ->where('source_id', $source->getKey())
                ->exists();

            if ($alreadyPosted) {
                throw new DomainException('This record has already been posted to the ledger.');
            }

            $current = (string) $locked->balance;

            if ($type === LedgerEntryType::Credit) {
                $after = bcadd($current, $amount, 2);
            } else {
                $after = bcsub($current, $amount, 2);

                if (bccomp($after, '0', 2) < 0) {
                    throw new InsufficientFundBalance($locked, $current, $amount);
                }
            }

            // Query update: balance is not fillable and its change is recorded by the ledger line itself.
            Fund::withTrashed()->whereKey($fundId)->update(['balance' => $after]);

            return FundLedgerEntry::query()->create([
                'fund_id' => $fundId,
                'entry_type' => $type,
                'amount' => $amount,
                'balance_after' => $after,
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
                'description' => $description,
                'posted_by' => $by?->getKey(),
                'posted_at' => now(),
            ]);
        });
    }

    private function normalise(string $amount): string
    {
        $amount = Money::normalise($amount);

        if (bccomp($amount, '0', 2) <= 0) {
            throw new DomainException('A ledger amount must be more than zero.');
        }

        return $amount;
    }
}
