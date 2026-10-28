<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DonationStatus;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\DonationRefund;
use App\Models\Fund;
use App\Models\FundLedgerEntry;

/**
 * Proves the money records agree with each other (FR-FIN-01, BR-03, BR-05, BR-07).
 * Read-only: it reports problems, it never fixes them. Used by `ledger:verify`
 * (nightly) and by the tests.
 *
 * Checks:
 * - Funds: stored balance = sum of ledger lines = last running balance; every
 *   running balance = previous + this line; never below zero.
 * - Donations: paid / partly refunded ones have exactly one credit for their full
 *   amount; pending / failed / cancelled ones have none; refunded_amount = sum of refunds.
 * - Refunds: each has exactly one debit for its amount.
 * - Campaigns: raised_amount = net of counted donations; donor_count = distinct donors.
 *
 * All sums use bcmath on decimal strings, never SQL SUM (SQLite returns floats).
 */
class LedgerAuditService
{
    /**
     * @return list<string> one line per problem; empty when everything agrees
     */
    public function problems(): array
    {
        return [...$this->fundProblems(), ...$this->donationProblems(), ...$this->refundProblems(), ...$this->campaignProblems()];
    }

    /**
     * @return list<string>
     */
    public function fundProblems(): array
    {
        $problems = [];

        foreach (Fund::withTrashed()->orderBy('id')->get() as $fund) {
            $running = '0.00';

            foreach (FundLedgerEntry::query()->where('fund_id', $fund->id)->orderBy('id')->lazy(500) as $entry) {
                $running = bcadd($running, $entry->signedAmount(), 2);

                if (bccomp($running, (string) $entry->balance_after, 2) !== 0) {
                    $problems[] = "Fund {$fund->code}: ledger line #{$entry->id} says balance {$entry->balance_after}, expected {$running}.";
                }

                if (bccomp($running, '0', 2) < 0) {
                    $problems[] = "Fund {$fund->code}: balance went below zero at ledger line #{$entry->id}.";
                }
            }

            if (bccomp((string) $fund->balance, $running, 2) !== 0) {
                $problems[] = "Fund {$fund->code}: stored balance {$fund->balance}, ledger total {$running}.";
            }
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    public function donationProblems(): array
    {
        $problems = [];
        $counted = [DonationStatus::Paid, DonationStatus::PartiallyRefunded, DonationStatus::Refunded];

        foreach (Donation::query()->with(['ledgerEntries', 'refunds'])->orderBy('id')->lazy(500) as $donation) {
            $credits = $donation->ledgerEntries;

            if (in_array($donation->status, $counted, true)) {
                if ($credits->count() !== 1) {
                    $problems[] = "Donation {$donation->donation_no}: has {$credits->count()} ledger credits, expected 1.";
                } elseif (bccomp((string) $credits->first()?->amount, (string) $donation->amount, 2) !== 0
                    || $credits->first()?->fund_id !== $donation->fund_id) {
                    $problems[] = "Donation {$donation->donation_no}: ledger credit does not match its amount or fund.";
                }
            } elseif ($credits->isNotEmpty()) {
                $problems[] = "Donation {$donation->donation_no}: is {$donation->status->value} but has a ledger credit.";
            }

            $refunded = '0.00';
            foreach ($donation->refunds as $refund) {
                $refunded = bcadd($refunded, (string) $refund->amount, 2);
            }

            if (bccomp($refunded, (string) $donation->refunded_amount, 2) !== 0) {
                $problems[] = "Donation {$donation->donation_no}: refunded_amount {$donation->refunded_amount}, refunds total {$refunded}.";
            }

            $expected = match (true) {
                bccomp($refunded, '0', 2) === 0 => null,
                bccomp($refunded, (string) $donation->amount, 2) === 0 => DonationStatus::Refunded,
                default => DonationStatus::PartiallyRefunded,
            };

            if ($expected !== null && $donation->status !== $expected) {
                $problems[] = "Donation {$donation->donation_no}: status {$donation->status->value}, refunds say {$expected->value}.";
            }
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    public function refundProblems(): array
    {
        $problems = [];

        foreach (DonationRefund::query()->with('ledgerEntry')->orderBy('id')->lazy(500) as $refund) {
            $debit = $refund->ledgerEntry;

            if ($debit === null) {
                $problems[] = "Refund #{$refund->id}: has no ledger debit.";
            } elseif (bccomp((string) $debit->amount, (string) $refund->amount, 2) !== 0 || $debit->fund_id !== $refund->fund_id) {
                $problems[] = "Refund #{$refund->id}: ledger debit does not match its amount or fund.";
            }
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    public function campaignProblems(): array
    {
        $problems = [];

        foreach (Campaign::withTrashed()->orderBy('id')->get() as $campaign) {
            $raised = '0.00';
            $donors = [];

            foreach (Donation::query()->counted()->where('campaign_id', $campaign->id)->lazy(500) as $donation) {
                $raised = bcadd($raised, $donation->netAmount(), 2);
                $donors[$donation->donor_id] = true;
            }

            if (bccomp((string) $campaign->raised_amount, $raised, 2) !== 0) {
                $problems[] = "Campaign \"{$campaign->title}\": raised_amount {$campaign->raised_amount}, donations total {$raised}.";
            }

            if ($campaign->donor_count !== count($donors)) {
                $problems[] = "Campaign \"{$campaign->title}\": donor_count {$campaign->donor_count}, actual ".count($donors).'.';
            }
        }

        return $problems;
    }
}
