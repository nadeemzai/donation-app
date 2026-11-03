<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PledgePeriodStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One due date of a pledge (Week 8 Day 2). Written only by PledgeReminderService.
 *
 * @property int $id
 * @property int $recurring_plan_id
 * @property Carbon $due_on
 * @property string $amount
 * @property PledgePeriodStatus $status
 * @property int|null $donation_id
 * @property Carbon|null $paid_at
 * @property Carbon|null $reminded_at
 * @property int $reminder_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RecurringPlan $plan
 * @property-read Donation|null $donation
 */
class RecurringPlanPeriod extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'due',
        'reminder_count' => 0,
    ];

    public function isPayable(): bool
    {
        return $this->status !== PledgePeriodStatus::Paid;
    }

    /**
     * @return BelongsTo<RecurringPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(RecurringPlan::class, 'recurring_plan_id');
    }

    /**
     * @return BelongsTo<Donation, $this>
     */
    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'amount' => 'decimal:2',
            'status' => PledgePeriodStatus::class,
            'paid_at' => 'datetime',
            'reminded_at' => 'datetime',
            'reminder_count' => 'integer',
        ];
    }
}
