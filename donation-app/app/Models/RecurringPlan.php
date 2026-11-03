<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DonationChannel;
use App\Enums\PaymentMethod;
use App\Enums\PledgeFrequency;
use App\Enums\PledgeStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\RecurringPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A pledge: "Rs 5,000 to Zakat every month" (Week 8, FR-DON-03).
 *
 * A promise, not money — nothing is counted until the donor pays. status, next_due_on and
 * the pause/cancel fields change only through RecurringPlanService (not mass-assignable).
 *
 * @property int $id
 * @property int $donor_id
 * @property int $fund_id
 * @property int|null $campaign_id
 * @property string $amount
 * @property string $currency
 * @property PledgeFrequency $frequency
 * @property int $day_of_month
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon|null $next_due_on
 * @property PledgeStatus $status
 * @property PaymentMethod|null $payment_method
 * @property DonationChannel $channel
 * @property string|null $notes
 * @property Carbon|null $paused_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Donor $donor
 * @property-read Fund $fund
 * @property-read Campaign|null $campaign
 */
#[Fillable(['donor_id', 'fund_id', 'campaign_id', 'amount', 'frequency', 'day_of_month', 'starts_on', 'ends_on', 'payment_method', 'channel', 'notes', 'created_by'])]
class RecurringPlan extends Model
{
    /** @use HasFactory<RecurringPlanFactory> */
    use Auditable, HasFactory;

    protected $attributes = [
        'status' => 'active',
        'currency' => 'PKR',
    ];

    /** "Zakat — Winter relief" or just the fund. */
    public function causeName(): string
    {
        return $this->campaign !== null ? $this->campaign->title : $this->fund->name;
    }

    /** Active and its due date has passed without being moved on. */
    public function isOverdue(): bool
    {
        return $this->status === PledgeStatus::Active
            && $this->next_due_on !== null
            && $this->next_due_on->lt(today());
    }

    /**
     * @param  Builder<RecurringPlan>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', PledgeStatus::Active->value);
    }

    /**
     * Active pledges due on or before $date.
     *
     * @param  Builder<RecurringPlan>  $query
     */
    public function scopeDueBy(Builder $query, Carbon|string $date): void
    {
        $query->where('status', PledgeStatus::Active->value)
            ->whereNotNull('next_due_on')
            ->whereDate('next_due_on', '<=', $date);
    }

    /**
     * @return BelongsTo<Donor, $this>
     */
    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Fund, $this>
     */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'frequency' => PledgeFrequency::class,
            'status' => PledgeStatus::class,
            'payment_method' => PaymentMethod::class,
            'channel' => DonationChannel::class,
            'day_of_month' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'next_due_on' => 'date',
            'paused_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
