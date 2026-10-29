<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\PaymentMethod;
use App\Events\DonationClosed;
use App\Events\DonationPaid;
use App\Events\DonationRefunded;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\DonationRefund;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Donation status changes (FR-DON-01/02, BR-01, BR-03, BR-09).
 *
 * Filament actions, Livewire components, API controllers and webhook handlers call
 * these methods; none of them set status, paid_at or totals themselves.
 *
 *   create()  → pending donation (nothing counted yet)
 *   confirm() → paid: ledger credit + fund balance + campaign totals, then DonationPaid
 *   fail()    → failed (gateway said no)
 *   cancel()  → cancelled (donor or staff gave up)
 *   refund()  → partially_refunded / refunded: refund record + ledger debit + campaign totals
 */
class DonationService
{
    /** Cancel reason prefix for rejected bank-transfer proof (the donor is told why). */
    public const PROOF_REJECTED_PREFIX = 'Proof rejected: ';

    public function __construct(private readonly FundLedgerService $ledger) {}

    /**
     * Record a new pending donation.
     *
     * - The fund must be active; the donor must exist and not be archived.
     * - Draft campaigns don't take donations. A closed or ended campaign's donation goes
     *   to the campaign's fund without the campaign (BR-09).
     * - Same idempotency_key again → the original donation comes back (safe retries
     *   from the mobile app and payment redirects).
     *
     * @param  array<string, mixed>  $attributes  any fillable Donation fields
     */
    public function create(array $attributes, ?User $by = null): Donation
    {
        $key = isset($attributes['idempotency_key']) ? (string) $attributes['idempotency_key'] : null;

        if ($key !== null && ($existing = Donation::query()->where('idempotency_key', $key)->first()) !== null) {
            return $this->sameRequest($existing, $attributes);
        }

        try {
            return DB::transaction(fn (): Donation => $this->insert($attributes, $by));
        } catch (UniqueConstraintViolationException $e) {
            // Two identical requests raced; the other one won.
            $existing = $key !== null ? Donation::query()->where('idempotency_key', $key)->first() : null;

            if ($existing === null) {
                throw $e;
            }

            return $this->sameRequest($existing, $attributes);
        }
    }

    /**
     * Pending → paid (BR-03). Credits the fund, updates the campaign and fires DonationPaid.
     *
     * Safe to call twice (a repeated webhook): an already-paid donation comes back
     * unchanged and nothing is posted again.
     */
    public function confirm(Donation $donation, ?User $by = null, ?string $reference = null, ?CarbonInterface $paidAt = null): Donation
    {
        return DB::transaction(function () use ($donation, $by, $reference, $paidAt): Donation {
            // Lock order: donation → fund (inside the ledger) → campaign.
            $locked = Donation::query()->lockForUpdate()->findOrFail($donation->getKey());

            if ($locked->status->isSettled()) {
                return $locked;
            }

            $this->assertTransition($locked, DonationStatus::Paid);

            $this->ledger->credit($locked->fund_id, (string) $locked->amount, $locked, "Donation {$locked->donation_no}", $by);

            if ($locked->campaign_id !== null) {
                $this->addToCampaignTotals($locked);
            }

            if ($reference !== null && $reference !== '') {
                $locked->reference = $reference;
            }

            $locked->status = DonationStatus::Paid;
            $locked->paid_at = $paidAt ?? now();
            $locked->confirmed_by = $by?->getKey();
            $locked->save();

            activity('audit')
                ->performedOn($locked)
                ->causedBy($by)
                ->event('confirmed')
                ->withProperties(['amount' => (string) $locked->amount, 'fund_id' => $locked->fund_id])
                ->log("Donation {$locked->donation_no} confirmed");

            DonationPaid::dispatch($locked);

            return $locked;
        });
    }

    /** Pending → failed. Repeating it is harmless. */
    public function fail(Donation $donation, ?string $reason = null, ?User $by = null): Donation
    {
        return $this->close($donation, DonationStatus::Failed, $reason, $by);
    }

    /** Pending → cancelled. Repeating it is harmless. */
    public function cancel(Donation $donation, ?string $reason = null, ?User $by = null): Donation
    {
        return $this->close($donation, DonationStatus::Cancelled, $reason, $by);
    }

    /**
     * Give back all or part of a paid donation (FR-DON-02, BR-05).
     *
     * - The donation's amount never changes; a DonationRefund is recorded and the fund
     *   is debited by the same amount.
     * - Refunds can't exceed what is left of the donation.
     * - If the fund has already spent the money, the refund is refused (BR-07) and nothing changes.
     * - The campaign total goes down; a donor stops being counted when nothing of theirs remains.
     *
     * @throws DomainException with a message staff can act on
     */
    public function refund(
        Donation $donation,
        string $amount,
        string $reason,
        PaymentMethod $method,
        ?User $by = null,
        ?string $reference = null,
    ): DonationRefund {
        $amount = Money::normalise($amount);

        if (bccomp($amount, '0', 2) <= 0) {
            throw new DomainException('The refund amount must be more than zero.');
        }

        if (trim($reason) === '') {
            throw new DomainException('Give a reason for the refund.');
        }

        return DB::transaction(function () use ($donation, $amount, $reason, $method, $by, $reference): DonationRefund {
            // Lock order: donation → fund (inside the ledger) → campaign.
            $locked = Donation::query()->lockForUpdate()->findOrFail($donation->getKey());

            if (! in_array($locked->status, [DonationStatus::Paid, DonationStatus::PartiallyRefunded], true)) {
                throw new DomainException("A {$locked->status->label()} donation cannot be refunded.");
            }

            $remaining = $locked->netAmount();

            if (bccomp($amount, $remaining, 2) > 0) {
                throw new DomainException('Only '.Money::rupees($remaining)." of {$locked->donation_no} can still be refunded.");
            }

            $refund = DonationRefund::query()->create([
                'donation_id' => $locked->getKey(),
                'fund_id' => $locked->fund_id,
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference !== null && $reference !== '' ? $reference : null,
                'reason' => trim($reason),
                'refunded_by' => $by?->getKey(),
                'refunded_at' => now(),
            ]);

            $this->ledger->debit($locked->fund_id, $amount, $refund, "Refund on donation {$locked->donation_no}", $by);

            $locked->refunded_amount = bcadd((string) $locked->refunded_amount, $amount, 2);
            $locked->status = bccomp($locked->netAmount(), '0', 2) === 0
                ? DonationStatus::Refunded
                : DonationStatus::PartiallyRefunded;
            $locked->save();

            if ($locked->campaign_id !== null) {
                $this->removeFromCampaignTotals($locked, $amount);
            }

            activity('audit')
                ->performedOn($locked)
                ->causedBy($by)
                ->event('refunded')
                ->withProperties([
                    'refund_id' => $refund->getKey(),
                    'amount' => $amount,
                    'method' => $method->value,
                    'reason' => trim($reason),
                ])
                ->log('Refund of '.Money::rupees($amount)." on donation {$locked->donation_no}");

            DonationRefunded::dispatch($refund);

            return $refund;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insert(array $attributes, ?User $by): Donation
    {
        $donation = new Donation($attributes);

        if ($donation->getAttribute('payment_method') === null || $donation->getAttribute('channel') === null) {
            throw new DomainException('Choose a payment method and channel.');
        }

        if (Donor::query()->whereKey($donation->getAttribute('donor_id'))->doesntExist()) {
            throw new DomainException('Donor not found, or the donor record is archived.');
        }

        $redirectedFrom = null;

        if ($donation->campaign_id !== null) {
            $campaign = Campaign::query()->find($donation->campaign_id)
                ?? throw new DomainException('Campaign not found.');

            if ($campaign->status === CampaignStatus::Draft) {
                throw new DomainException('This campaign is not live yet, so it cannot take donations.');
            }

            // BR-01: a campaign donation always goes to that campaign's fund.
            if ($donation->getAttribute('fund_id') === null) {
                $donation->fund_id = $campaign->fund_id;
            } elseif ((int) $donation->fund_id !== $campaign->fund_id) {
                throw new DomainException("This campaign's donations go to its own fund only.");
            }

            // BR-09: once a campaign has closed or ended, money goes to its fund, not the campaign.
            if ($campaign->status === CampaignStatus::Closed || $campaign->hasEnded()) {
                $redirectedFrom = $campaign;
                $donation->campaign_id = null;
            }
        }

        $fund = Fund::query()->find($donation->getAttribute('fund_id'))
            ?? throw new DomainException('Choose a fund for this donation.');

        if (! $fund->is_active) {
            throw new DomainException("The {$fund->name} fund is not taking donations right now.");
        }

        if ($by !== null && $donation->recorded_by === null) {
            $donation->recorded_by = (int) $by->getKey();
        }

        if ($donation->channel === DonationChannel::Admin && $donation->recorded_by === null) {
            throw new DomainException('A donation entered in the admin panel must record who entered it.');
        }

        if ($donation->payment_method->isOffline() && $donation->received_at === null) {
            $donation->received_at = now();
        }

        $donation->save();

        if ($redirectedFrom !== null) {
            activity('audit')
                ->performedOn($donation)
                ->causedBy($by)
                ->event('redirected_to_fund')
                ->withProperties(['campaign_id' => $redirectedFrom->getKey(), 'fund_id' => $donation->fund_id])
                ->log("Campaign \"{$redirectedFrom->title}\" is closed; donation credited to its fund");
        }

        return $donation;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function sameRequest(Donation $existing, array $attributes): Donation
    {
        $sameDonor = ! isset($attributes['donor_id']) || (int) $attributes['donor_id'] === $existing->donor_id;
        $sameAmount = ! isset($attributes['amount']) || bccomp((string) $attributes['amount'], (string) $existing->amount, 2) === 0;

        if (! $sameDonor || ! $sameAmount) {
            throw new DomainException('This request key was already used for a different donation.');
        }

        return $existing;
    }

    /** Caller holds the donation lock; the campaign row is locked here, after the fund. */
    private function addToCampaignTotals(Donation $donation): void
    {
        $campaign = Campaign::withTrashed()->lockForUpdate()->findOrFail($donation->campaign_id);

        // Counted only once per donor; this donation is still pending, so it isn't in the count.
        $firstFromDonor = Donation::query()
            ->counted()
            ->where('campaign_id', $campaign->getKey())
            ->where('donor_id', $donation->donor_id)
            ->doesntExist();

        // Query update: totals aren't audited fields, and a closed campaign may still receive
        // a donation that was started before it closed.
        Campaign::withTrashed()->whereKey($campaign->getKey())->update([
            'raised_amount' => bcadd((string) $campaign->raised_amount, (string) $donation->amount, 2),
            'donor_count' => $campaign->donor_count + ($firstFromDonor ? 1 : 0),
        ]);
    }

    /**
     * Caller holds the donation lock and has already saved the donation's new status,
     * so the "still counted" check below sees it.
     */
    private function removeFromCampaignTotals(Donation $donation, string $amount): void
    {
        $campaign = Campaign::withTrashed()->lockForUpdate()->findOrFail($donation->campaign_id);

        $raised = bcsub((string) $campaign->raised_amount, $amount, 2);

        $donorStillCounted = Donation::query()
            ->counted()
            ->where('campaign_id', $campaign->getKey())
            ->where('donor_id', $donation->donor_id)
            ->exists();

        Campaign::withTrashed()->whereKey($campaign->getKey())->update([
            'raised_amount' => bccomp($raised, '0', 2) < 0 ? '0.00' : $raised,
            'donor_count' => $donorStillCounted ? $campaign->donor_count : max(0, $campaign->donor_count - 1),
        ]);
    }

    private function close(Donation $donation, DonationStatus $next, ?string $reason, ?User $by): Donation
    {
        return DB::transaction(function () use ($donation, $next, $reason, $by): Donation {
            $locked = Donation::query()->lockForUpdate()->findOrFail($donation->getKey());

            if ($locked->status === $next) {
                return $locked;
            }

            $this->assertTransition($locked, $next);

            $locked->status = $next;

            if ($next === DonationStatus::Failed) {
                $locked->failed_at = now();
            } else {
                $locked->cancelled_at = now();
            }

            $locked->save();

            activity('audit')
                ->performedOn($locked)
                ->causedBy($by)
                ->event($next->value)
                ->withProperty('reason', $reason)
                ->log($reason !== null && $reason !== ''
                    ? "Donation {$locked->donation_no} {$next->value}: {$reason}"
                    : "Donation {$locked->donation_no} {$next->value}");

            DonationClosed::dispatch($locked, $next, $reason);

            return $locked;
        });
    }

    private function assertTransition(Donation $donation, DonationStatus $next): void
    {
        if (! $donation->status->canTransitionTo($next)) {
            throw new DomainException("A {$donation->status->label()} donation cannot become {$next->label()}.");
        }
    }
}
