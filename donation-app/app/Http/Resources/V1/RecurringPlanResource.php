<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Enums\PledgePeriodStatus;
use App\Models\RecurringPlan;
use App\Models\RecurringPlanPeriod;
use App\Services\DonorPledgeService;
use App\Services\PledgeReminderService;
use App\Support\ApiFormat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of the logged-in donor's pledges (Week 8 Day 3). `pay_now` is set when a due date is
 * unpaid: give with POST /donations and `pledge_period_id`, or open `web_url`.
 * Load "fund", "campaign" and "periods".
 *
 * @mixin RecurringPlan
 */
class RecurringPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var RecurringPlan $plan */
        $plan = $this->resource;
        $payable = app(DonorPledgeService::class)->payablePeriod($plan);
        $periods = $plan->relationLoaded('periods') ? $plan->periods : $plan->periods()->get();

        return [
            'id' => $plan->id,
            'status' => ApiFormat::enum($plan->status),
            'amount' => ApiFormat::money((string) $plan->amount, $plan->currency),
            'frequency' => ApiFormat::enum($plan->frequency),
            'fund' => ['code' => $plan->fund->code, 'name' => $plan->fund->name, 'is_zakat' => $plan->fund->is_zakat],
            'campaign' => $plan->campaign !== null ? ['slug' => $plan->campaign->slug, 'title' => $plan->campaign->title] : null,
            'starts_on' => ApiFormat::date($plan->starts_on),
            'ends_on' => ApiFormat::date($plan->ends_on),
            'next_due_on' => ApiFormat::date($plan->next_due_on),
            'payment_method' => ApiFormat::enum($plan->payment_method),
            'is_overdue' => $plan->isOverdue(),
            'payments' => [
                'paid' => $periods->where('status', PledgePeriodStatus::Paid)->count(),
                'missed' => $periods->where('status', PledgePeriodStatus::Missed)->count(),
                'due' => $periods->where('status', PledgePeriodStatus::Due)->count(),
            ],
            'pay_now' => $payable !== null ? [
                'pledge_period_id' => $payable->id,
                'due_on' => ApiFormat::date($payable->due_on),
                'amount' => ApiFormat::money((string) $payable->amount, $plan->currency),
                'web_url' => PledgeReminderService::payUrl($payable),
            ] : null,
            'recent' => $periods->take(12)->map(fn (RecurringPlanPeriod $p): array => [
                'due_on' => ApiFormat::date($p->due_on),
                'status' => ApiFormat::enum($p->status),
            ])->values()->all(),
            'created_at' => ApiFormat::time($plan->created_at),
        ];
    }
}
