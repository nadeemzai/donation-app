<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\DonationChannel;
use App\Enums\PaymentMethod;
use App\Enums\PledgeFrequency;
use App\Enums\PledgeStatus;
use App\Models\Campaign;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\RecurringPlan;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pledges (Week 8, FR-DON-03). The only place a pledge changes status or due date.
 *
 *   create()  → active, first due on its start date
 *   update()  → amount, usual payment method, end date, notes (future periods only)
 *   pause()   → no reminders until resumed
 *   resume()  → active again; due dates missed while paused are skipped, not owed
 *   cancel()  → final
 *   advance() → move to the next period (Day 2: after a reminder's donation is paid);
 *               completes the pledge once its end date is passed
 *
 * Due dates fall on day_of_month (1–28) so every month has one. Every change is audited.
 */
class RecurringPlanService
{
    /**
     * @param  array<string, mixed>  $attributes  donor_id, fund_id|campaign_id, amount, frequency,
     *                                            starts_on, ends_on?, payment_method?, notes?, channel?
     */
    public function create(array $attributes, ?User $by = null): RecurringPlan
    {
        $amount = $this->amount((string) ($attributes['amount'] ?? ''));
        $given = $attributes['frequency'] ?? null;
        $frequency = $given instanceof PledgeFrequency
            ? $given
            : (PledgeFrequency::tryFrom(is_string($given) ? $given : '') ?? throw new DomainException('Choose how often the pledge is due.'));

        $startsOn = CarbonImmutable::parse((string) ($attributes['starts_on'] ?? today()->toDateString()))->startOfDay();

        if ($startsOn->lt(today())) {
            throw new DomainException('A pledge cannot start in the past.');
        }

        $endsOn = filled($attributes['ends_on'] ?? null) ? CarbonImmutable::parse((string) $attributes['ends_on'])->startOfDay() : null;

        if ($endsOn !== null && $endsOn->lte($startsOn)) {
            throw new DomainException('The end date must be after the start date.');
        }

        $donor = Donor::query()->find((int) ($attributes['donor_id'] ?? 0))
            ?? throw new DomainException('Donor not found, or the donor record is archived.');

        [$fund, $campaign] = $this->cause($attributes);

        $plan = new RecurringPlan([
            'donor_id' => $donor->id,
            'fund_id' => $fund->id,
            'campaign_id' => $campaign?->id,
            'amount' => $amount,
            'frequency' => $frequency,
            'day_of_month' => min(28, $startsOn->day),
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $endsOn?->toDateString(),
            'payment_method' => $this->method($attributes['payment_method'] ?? null),
            'channel' => $attributes['channel'] ?? DonationChannel::Admin,
            'notes' => filled($attributes['notes'] ?? null) ? trim((string) $attributes['notes']) : null,
            'created_by' => $by?->getKey(),
        ]);
        $plan->status = PledgeStatus::Active;
        $plan->next_due_on = Carbon::instance($startsOn);
        $plan->save();

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $changes  amount?, payment_method?, ends_on?, notes?
     */
    public function update(RecurringPlan $plan, array $changes, ?User $by = null): RecurringPlan
    {
        return DB::transaction(function () use ($plan, $changes): RecurringPlan {
            $locked = RecurringPlan::query()->lockForUpdate()->findOrFail($plan->getKey());

            if ($locked->status->isFinal()) {
                throw new DomainException("A {$locked->status->label()} pledge cannot be changed.");
            }

            if (array_key_exists('amount', $changes)) {
                $locked->amount = $this->amount((string) $changes['amount']);
            }

            if (array_key_exists('payment_method', $changes)) {
                $locked->payment_method = $this->method($changes['payment_method']);
            }

            if (array_key_exists('ends_on', $changes)) {
                $endsOn = filled($changes['ends_on']) ? CarbonImmutable::parse((string) $changes['ends_on'])->startOfDay() : null;

                if ($endsOn !== null && $endsOn->lte(CarbonImmutable::instance($locked->starts_on))) {
                    throw new DomainException('The end date must be after the start date.');
                }

                $locked->ends_on = $endsOn !== null ? Carbon::instance($endsOn) : null;
            }

            if (array_key_exists('notes', $changes)) {
                $locked->notes = filled($changes['notes']) ? trim((string) $changes['notes']) : null;
            }

            $locked->save(); // audited: before → after

            return $locked;
        });
    }

    public function pause(RecurringPlan $plan, ?string $reason = null, ?User $by = null): RecurringPlan
    {
        return $this->move($plan, PledgeStatus::Paused, $by, $reason, function (RecurringPlan $p): void {
            $p->paused_at = now();
        });
    }

    /** Due dates that passed while paused are skipped: the next one is today or later. */
    public function resume(RecurringPlan $plan, ?User $by = null): RecurringPlan
    {
        return $this->move($plan, PledgeStatus::Active, $by, null, function (RecurringPlan $p): void {
            $p->paused_at = null;
            $next = CarbonImmutable::instance($p->next_due_on ?? $p->starts_on);

            while ($next->lt(today())) {
                $next = self::nextDue($next, $p->frequency, $p->day_of_month);
            }

            $p->next_due_on = Carbon::instance($next);
            $this->completeIfEnded($p);
        });
    }

    public function cancel(RecurringPlan $plan, string $reason, ?User $by = null): RecurringPlan
    {
        if (trim($reason) === '') {
            throw new DomainException('Give a reason for cancelling the pledge.');
        }

        return $this->move($plan, PledgeStatus::Cancelled, $by, $reason, function (RecurringPlan $p) use ($reason): void {
            $p->cancelled_at = now();
            $p->cancel_reason = mb_substr(trim($reason), 0, 255);
            $p->next_due_on = null;
        });
    }

    /**
     * Move an active pledge to its next period (called when a period is settled — Day 2).
     * Completes the pledge when the next date would fall after its end date.
     */
    public function advance(RecurringPlan $plan): RecurringPlan
    {
        return DB::transaction(function () use ($plan): RecurringPlan {
            $locked = RecurringPlan::query()->lockForUpdate()->findOrFail($plan->getKey());

            if ($locked->status !== PledgeStatus::Active || $locked->next_due_on === null) {
                return $locked;
            }

            $locked->next_due_on = Carbon::instance(self::nextDue($locked->next_due_on, $locked->frequency, $locked->day_of_month));
            $this->completeIfEnded($locked);
            $locked->save();

            return $locked;
        });
    }

    /** The due date one period after $from, on $day (1–28). */
    public static function nextDue(CarbonInterface $from, PledgeFrequency $frequency, int $day): CarbonImmutable
    {
        return CarbonImmutable::instance($from)
            ->startOfMonth()
            ->addMonthsNoOverflow($frequency->months())
            ->setDay(max(1, min(28, $day)))
            ->startOfDay();
    }

    private function completeIfEnded(RecurringPlan $plan): void
    {
        if ($plan->ends_on !== null && $plan->next_due_on !== null && $plan->next_due_on->gt($plan->ends_on)) {
            $plan->status = PledgeStatus::Completed;
            $plan->next_due_on = null;
        }
    }

    /**
     * @param  callable(RecurringPlan): void  $apply
     */
    private function move(RecurringPlan $plan, PledgeStatus $next, ?User $by, ?string $reason, callable $apply): RecurringPlan
    {
        return DB::transaction(function () use ($plan, $next, $by, $reason, $apply): RecurringPlan {
            $locked = RecurringPlan::query()->lockForUpdate()->findOrFail($plan->getKey());

            if ($locked->status === $next) {
                return $locked; // repeating it is harmless
            }

            if (! $locked->status->canTransitionTo($next)) {
                throw new DomainException("A {$locked->status->label()} pledge cannot be {$this->verb($next)}.");
            }

            $locked->status = $next;
            $apply($locked);
            $locked->save();

            activity('audit')
                ->performedOn($locked)
                ->causedBy($by)
                ->event('pledge_'.$next->value)
                ->withProperty('reason', $reason)
                ->log("Pledge #{$locked->id} {$this->verb($next)}".($reason !== null && $reason !== '' ? ": {$reason}" : ''));

            return $locked;
        });
    }

    private function verb(PledgeStatus $status): string
    {
        return match ($status) {
            PledgeStatus::Active => 'resumed',
            PledgeStatus::Paused => 'paused',
            PledgeStatus::Cancelled => 'cancelled',
            PledgeStatus::Completed => 'completed',
        };
    }

    private function amount(string $amount): string
    {
        try {
            $amount = Money::normalise(preg_replace('/[\s,]/', '', $amount) ?? '');
        } catch (InvalidArgumentException) {
            throw new DomainException('Enter the pledge amount in rupees, e.g. 5000.');
        }

        if (bccomp($amount, (string) config('donation.donate.min'), 2) < 0) {
            throw new DomainException('The smallest pledge is Rs '.number_format((int) config('donation.donate.min')).'.');
        }

        return $amount;
    }

    private function method(mixed $value): ?PaymentMethod
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof PaymentMethod ? $value : (PaymentMethod::tryFrom((string) $value) ?? throw new DomainException('Unknown payment method.'));
    }

    /**
     * BR-01: a campaign pledge goes to the campaign's fund. Only live campaigns and active funds.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: Fund, 1: Campaign|null}
     */
    private function cause(array $attributes): array
    {
        if (filled($attributes['campaign_id'] ?? null)) {
            $campaign = Campaign::query()->find((int) $attributes['campaign_id']) ?? throw new DomainException('Campaign not found.');

            if ($campaign->status !== CampaignStatus::Live || $campaign->hasEnded()) {
                throw new DomainException('Pledges can only be made to a live campaign.');
            }

            $fund = Fund::query()->findOrFail($campaign->fund_id);

            if (filled($attributes['fund_id'] ?? null) && (int) $attributes['fund_id'] !== $fund->id) {
                throw new DomainException("This campaign's donations go to its own fund only.");
            }
        } else {
            $campaign = null;
            $fund = Fund::query()->find((int) ($attributes['fund_id'] ?? 0)) ?? throw new DomainException('Choose a fund for the pledge.');
        }

        if (! $fund->is_active) {
            throw new DomainException("The {$fund->name} fund is not taking donations right now.");
        }

        return [$fund, $campaign];
    }
}
