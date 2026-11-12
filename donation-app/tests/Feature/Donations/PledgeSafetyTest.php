<?php

declare(strict_types=1);

use App\Enums\MessageChannel;
use App\Enums\PledgeFrequency;
use App\Enums\PledgeStatus;
use App\Enums\Role;
use App\Filament\Resources\RecurringPlans\Pages\ViewRecurringPlan;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\RecurringPlan;
use App\Models\RecurringPlanPeriod;
use App\Services\PledgeReminderService;
use App\Services\RecurringPlanService;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

// Week 8 Day 5 — pledges: no pay links that would fail; Finance can resend or share the link

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2027-01-10 09:10', 'Asia/Karachi')));

/**
 * @param  array<string, mixed>  $overrides
 */
function safePledge(array $overrides = []): RecurringPlan
{
    return app(RecurringPlanService::class)->create(array_merge([
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => Fund::factory()->create(['name' => 'Water Fund'])->id,
        'amount' => '2000',
        'frequency' => PledgeFrequency::Monthly->value,
        'starts_on' => today()->toDateString(),
    ], $overrides));
}

function reminderCount(): int
{
    return MessageLog::query()->where('template', MessageTemplate::PLEDGE_REMINDER)->where('channel', MessageChannel::Email->value)->count();
}

it('pauses a pledge whose donor was archived instead of reminding them', function () {
    $plan = safePledge();
    $plan->donor->delete();

    $stats = app(PledgeReminderService::class)->run();

    expect($stats)->toMatchArray(['paused' => 1, 'opened' => 0])
        ->and($plan->fresh()?->status)->toBe(PledgeStatus::Paused)
        ->and(RecurringPlanPeriod::query()->count())->toBe(0)
        ->and(reminderCount())->toBe(0)
        ->and(Activity::query()->where('event', 'pledge_paused')->sole()->description)->toContain('donor record is archived');
});

it('pauses a pledge whose fund stopped taking donations', function () {
    $plan = safePledge();
    $plan->fund->forceFill(['is_active' => false])->save();

    app(PledgeReminderService::class)->run();

    expect($plan->fresh()?->status)->toBe(PledgeStatus::Paused)
        ->and(Activity::query()->where('event', 'pledge_paused')->sole()->description)->toContain('Water Fund fund is not taking donations');
});

it('lets Finance send the reminder again, even after the automatic two', function () {
    $plan = safePledge();
    app(PledgeReminderService::class)->run();
    RecurringPlanPeriod::query()->update(['reminder_count' => 2]);
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(ViewRecurringPlan::class, ['record' => $plan->getRouteKey()])
        ->callAction('remind')
        ->assertNotified('Reminder sent');

    expect(reminderCount())->toBe(2)
        ->and(RecurringPlanPeriod::sole()->reminder_count)->toBe(3);
});

it('shows Finance the pay link to share, and hides it from view-only roles', function () {
    $plan = safePledge();
    app(PledgeReminderService::class)->run();

    $this->actingAs(staffUser(Role::Finance))
        ->get("/admin/recurring-plans/{$plan->id}")
        ->assertSee('Pay link for the unpaid due date');

    $this->actingAs(staffUser(Role::Manager));
    $this->get("/admin/recurring-plans/{$plan->id}")->assertDontSee('Pay link for the unpaid due date');
    Livewire::test(ViewRecurringPlan::class, ['record' => $plan->getRouteKey()])->assertActionHidden('remind');
});

it('offers no reminder before the first due date has come', function () {
    $plan = safePledge(['starts_on' => today()->addWeek()->toDateString()]);
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(ViewRecurringPlan::class, ['record' => $plan->getRouteKey()])->assertActionHidden('remind');
});
