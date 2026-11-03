<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DonationChannel;
use App\Enums\PledgePeriodStatus;
use App\Models\Donor;
use App\Models\RecurringPlan;
use App\Models\RecurringPlanPeriod;
use DomainException;

/**
 * Donors managing their own pledges — portal and app (Week 8 Day 3). The same rules as the
 * admin (RecurringPlanService), plus: a donor only ever touches their own pledges, and at
 * most MAX_ACTIVE are running at once (stops a typo — or a bot — creating dozens).
 */
class DonorPledgeService
{
    public const MAX_ACTIVE = 10;

    public const ACTIONS = ['pause', 'resume', 'cancel'];

    public function __construct(private readonly RecurringPlanService $plans) {}

    /**
     * @param  array<string, mixed>  $input  fund_id|campaign_id, amount, frequency, starts_on?, payment_method?
     */
    public function start(Donor $donor, array $input, DonationChannel $channel): RecurringPlan
    {
        $running = RecurringPlan::query()->where('donor_id', $donor->id)->whereIn('status', ['active', 'paused'])->count();

        if ($running >= self::MAX_ACTIVE) {
            throw new DomainException(__('You already have :max pledges. Please cancel one before adding another, or contact us.', ['max' => self::MAX_ACTIVE]));
        }

        return $this->plans->create([
            'donor_id' => $donor->id,
            'fund_id' => $input['fund_id'] ?? null,
            'campaign_id' => $input['campaign_id'] ?? null,
            'amount' => $input['amount'] ?? null,
            'frequency' => $input['frequency'] ?? null,
            'starts_on' => $input['starts_on'] ?? today()->toDateString(),
            'payment_method' => $input['payment_method'] ?? null,
            'channel' => $channel,
        ]);
    }

    /** pause | resume | cancel, and/or a new amount. */
    public function change(Donor $donor, RecurringPlan $plan, ?string $action, ?string $amount = null, ?string $reason = null): RecurringPlan
    {
        $this->assertOwn($donor, $plan);

        if ($amount !== null && $amount !== '') {
            $plan = $this->plans->update($plan, ['amount' => $amount]);
        }

        $note = 'by the donor'.($reason !== null && trim($reason) !== '' ? ': '.trim($reason) : '');

        return match ($action) {
            null, '' => $plan,
            'pause' => $this->plans->pause($plan, 'Paused '.$note),
            'resume' => $this->plans->resume($plan),
            'cancel' => $this->plans->cancel($plan, 'Cancelled '.$note),
            default => throw new DomainException(__('Unknown action.')),
        };
    }

    /** The due date the donor can pay now: the newest unpaid one, if any. */
    public function payablePeriod(RecurringPlan $plan): ?RecurringPlanPeriod
    {
        return RecurringPlanPeriod::query()
            ->where('recurring_plan_id', $plan->id)
            ->whereIn('status', [PledgePeriodStatus::Due->value, PledgePeriodStatus::Missed->value])
            ->latest('due_on')
            ->first();
    }

    /** Someone else's pledge is "not found", never "forbidden". */
    public function assertOwn(Donor $donor, RecurringPlan $plan): void
    {
        abort_unless($plan->donor_id === $donor->id, 404);
    }
}
