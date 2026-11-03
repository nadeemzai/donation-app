<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PledgePeriodStatus;
use App\Enums\PledgeStatus;
use App\Models\Donation;
use App\Models\MessageTemplate;
use App\Models\RecurringPlan;
use App\Models\RecurringPlanPeriod;
use App\Services\Messaging\NotificationService;
use App\Services\Messaging\TemplateRenderer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Pledge reminders and fulfilment (Week 8 Day 2).
 *
 * Every morning (pledges:remind):
 *  1. Each active pledge whose next due date has come gets a period for that date and a
 *     reminder (WhatsApp if opted in, and email) with a personal pay link. The pledge moves
 *     on to its next due date straight away, so one late payment never stops the schedule.
 *     An older period that is still unpaid becomes "missed" (it can still be paid late).
 *  2. A period still unpaid three days after its reminder gets one more reminder.
 *
 * When a donation made from a pay link is confirmed (DonationPaid), its period is marked
 * paid. Nothing here touches money: the donation does that, like any other.
 */
class PledgeReminderService
{
    /** Second reminder this many days after the first. */
    public const SECOND_REMINDER_AFTER_DAYS = 3;

    /** At most this many reminders per period. */
    public const MAX_REMINDERS = 2;

    /** A pay link works this long after its due date. */
    public const PAY_LINK_DAYS = 60;

    public function __construct(
        private readonly RecurringPlanService $plans,
        private readonly NotificationService $notifications,
        private readonly TemplateRenderer $renderer,
    ) {}

    /**
     * @return array{opened: int, reminded: int, second_reminders: int, failed: int, paused: int}
     */
    public function run(): array
    {
        $stats = ['opened' => 0, 'reminded' => 0, 'second_reminders' => 0, 'failed' => 0, 'paused' => 0];

        // lazyById, not each(): the set shrinks as pledges move on, and offset paging would skip some.
        RecurringPlan::query()->dueBy(today())->lazyById(100)->each(function (RecurringPlan $plan) use (&$stats): void {
            // Don't send a pay link the donation form would refuse: pause instead, so staff see it.
            $problem = $this->cannotRemind($plan);

            if ($problem !== null) {
                $this->plans->pause($plan, $problem);
                $stats['paused']++;

                return;
            }

            try {
                $period = $this->openPeriod($plan);
            } catch (Throwable $e) {
                report($e);
                $stats['failed']++;

                return;
            }

            if ($period !== null) {
                $stats['opened']++;
                $stats['reminded'] += $this->remind($period) ? 1 : 0;
            }
        });

        RecurringPlanPeriod::query()
            ->where('status', PledgePeriodStatus::Due->value)
            ->where('reminder_count', 1)
            ->where('reminded_at', '<=', now()->subDays(self::SECOND_REMINDER_AFTER_DAYS))
            ->whereHas('plan', fn ($q) => $q->where('status', PledgeStatus::Active->value))
            ->lazyById(100)
            ->each(function (RecurringPlanPeriod $period) use (&$stats): void {
                $stats['second_reminders'] += $this->remind($period) ? 1 : 0;
            });

        return $stats;
    }

    /**
     * Open the period for the pledge's current due date and move the pledge on. If the job
     * didn't run for a while, every passed date gets a period; only the newest is reminded.
     */
    public function openPeriod(RecurringPlan $plan): ?RecurringPlanPeriod
    {
        return DB::transaction(function () use ($plan): ?RecurringPlanPeriod {
            $locked = RecurringPlan::query()->lockForUpdate()->findOrFail($plan->getKey());
            $latest = null;

            for ($i = 0; $i < 36 && $locked->status === PledgeStatus::Active && $locked->next_due_on !== null && $locked->next_due_on->lte(today()); $i++) {
                // Anything still unpaid from before is now missed (it can still be paid late).
                RecurringPlanPeriod::query()
                    ->where('recurring_plan_id', $locked->id)
                    ->where('status', PledgePeriodStatus::Due->value)
                    ->update(['status' => PledgePeriodStatus::Missed->value, 'updated_at' => now()]);

                $latest = RecurringPlanPeriod::query()->firstOrCreate(
                    ['recurring_plan_id' => $locked->id, 'due_on' => $locked->next_due_on->toDateString()],
                    ['amount' => (string) $locked->amount, 'status' => PledgePeriodStatus::Due->value],
                );

                $locked = $this->plans->advance($locked);
            }

            return $latest;
        });
    }

    /**
     * Send (or repeat) the reminder for a period. False when nothing could go out.
     * $byStaff: a reminder Finance sends by hand — allowed past the automatic limit.
     */
    public function remind(RecurringPlanPeriod $period, bool $byStaff = false): bool
    {
        $period->loadMissing(['plan.donor', 'plan.fund', 'plan.campaign']);

        if (! $period->isPayable() || (! $byStaff && $period->reminder_count >= self::MAX_REMINDERS)) {
            return false;
        }

        $logs = $this->notifications->send(
            $period->plan->donor,
            MessageTemplate::PLEDGE_REMINDER,
            $this->renderer->varsForPeriod($period, self::payUrl($period)),
            $period,
        );

        $period->forceFill(['reminded_at' => now(), 'reminder_count' => $period->reminder_count + 1])->save();

        return $logs !== [];
    }

    /** Why a pledge can't be reminded today (archived donor, closed fund), or null. */
    public function cannotRemind(RecurringPlan $plan): ?string
    {
        $plan->loadMissing(['donor', 'fund']);

        return match (true) {
            $plan->donor->trashed() => 'Paused automatically: the donor record is archived.',
            $plan->fund->trashed() || ! $plan->fund->is_active => "Paused automatically: the {$plan->fund->name} fund is not taking donations.",
            default => null,
        };
    }

    /** Called when a donation is confirmed: the period it pays is now fulfilled. */
    public function markPaid(Donation $donation): ?RecurringPlanPeriod
    {
        if ($donation->recurring_plan_period_id === null || ! $donation->status->isSettled()) {
            return null;
        }

        return DB::transaction(function () use ($donation): ?RecurringPlanPeriod {
            $period = RecurringPlanPeriod::query()->lockForUpdate()->find($donation->recurring_plan_period_id);

            if ($period === null || $period->status === PledgePeriodStatus::Paid) {
                return $period; // already settled by another donation — that one counts
            }

            $period->forceFill([
                'status' => PledgePeriodStatus::Paid->value,
                'donation_id' => $donation->id,
                'paid_at' => $donation->paid_at ?? now(),
            ])->save();

            activity('audit')
                ->performedOn($period->plan)
                ->event('pledge_period_paid')
                ->withProperties(['period_id' => $period->id, 'due_on' => $period->due_on->toDateString(), 'donation_no' => $donation->donation_no])
                ->log("Pledge #{$period->recurring_plan_id} paid for {$period->due_on->format('d M Y')} by {$donation->donation_no}");

            return $period;
        });
    }

    /** The personal link in the reminder: opens the donate page ready to pay this period. */
    public static function payUrl(RecurringPlanPeriod $period): string
    {
        return URL::temporarySignedRoute(
            'donate',
            Carbon::instance($period->due_on)->addDays(self::PAY_LINK_DAYS)->endOfDay(),
            ['pledge' => $period->id],
        );
    }
}
