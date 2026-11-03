<?php

declare(strict_types=1);

use App\Enums\PledgeFrequency;
use App\Enums\PledgeStatus;
use App\Enums\Role;
use App\Filament\Resources\RecurringPlans\Pages\CreateRecurringPlan;
use App\Filament\Resources\RecurringPlans\Pages\EditRecurringPlan;
use App\Filament\Resources\RecurringPlans\Pages\ListRecurringPlans;
use App\Filament\Resources\RecurringPlans\Pages\ViewRecurringPlan;
use App\Models\Campaign;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\RecurringPlan;
use App\Services\DonorMergeService;
use App\Services\RecurringPlanService;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

// Week 8 Day 1 — pledges (recurring donations): rules, due dates, admin screen

function pledges(): RecurringPlanService
{
    return app(RecurringPlanService::class);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function newPledge(array $overrides = []): RecurringPlan
{
    return pledges()->create(array_merge([
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => '5000',
        'frequency' => PledgeFrequency::Monthly->value,
        'starts_on' => today()->toDateString(),
    ], $overrides));
}

// ── rules ───────────────────────────────────────────────────────────────

it('sets up an active pledge, first due on its start date', function () {
    $plan = newPledge(['amount' => '5,000', 'starts_on' => today()->addDays(3)->toDateString()]);

    expect($plan->fresh())
        ->status->toBe(PledgeStatus::Active)
        ->amount->toBe('5000.00')
        ->next_due_on->toDateString()->toBe(today()->addDays(3)->toDateString())
        ->and(Activity::query()->where('subject_type', $plan->getMorphClass())->where('event', 'created')->exists())->toBeTrue();
});

it('falls on the 28th at the latest, so every month has a due date', function () {
    $this->travelTo(CarbonImmutable::parse('2027-01-31 10:00', 'Asia/Karachi'));
    $plan = newPledge();

    expect($plan->day_of_month)->toBe(28);

    $plan = pledges()->advance($plan);
    expect($plan->next_due_on?->toDateString())->toBe('2027-02-28');

    $plan = pledges()->advance($plan);
    expect($plan->next_due_on?->toDateString())->toBe('2027-03-28');
});

it('works out the next due date for each frequency', function (PledgeFrequency $frequency, string $next) {
    expect(RecurringPlanService::nextDue(CarbonImmutable::parse('2027-01-15'), $frequency, 15)->toDateString())->toBe($next);
})->with([
    'monthly' => [PledgeFrequency::Monthly, '2027-02-15'],
    'quarterly' => [PledgeFrequency::Quarterly, '2027-04-15'],
    'yearly' => [PledgeFrequency::Yearly, '2028-01-15'],
]);

it('sends a campaign pledge to the campaign\'s fund', function () {
    $campaign = Campaign::factory()->live()->create();

    $plan = newPledge(['fund_id' => null, 'campaign_id' => $campaign->id]);

    expect($plan)->campaign_id->toBe($campaign->id)->fund_id->toBe($campaign->fund_id)
        ->and($plan->causeName())->toBe($campaign->title);
});

it('refuses pledges that break the rules', function (Closure $attributes, string $message) {
    expect(fn () => newPledge($attributes()))->toThrow(DomainException::class, $message);

    expect(RecurringPlan::query()->count())->toBe(0);
})->with([
    'starts in the past' => [fn () => ['starts_on' => today()->subDay()->toDateString()], 'cannot start in the past'],
    'below the minimum' => [fn () => ['amount' => '50'], 'The smallest pledge'],
    'not a number' => [fn () => ['amount' => 'lots'], 'Enter the pledge amount'],
    'no frequency' => [fn () => ['frequency' => 'weekly'], 'Choose how often'],
    'ends before it starts' => [fn () => ['ends_on' => today()->toDateString()], 'end date must be after'],
    'inactive fund' => [fn () => ['fund_id' => Fund::factory()->inactive()->create()->id], 'not taking donations'],
    'closed campaign' => [fn () => ['fund_id' => null, 'campaign_id' => Campaign::factory()->closed()->create()->id], 'live campaign'],
    'draft campaign' => [fn () => ['fund_id' => null, 'campaign_id' => Campaign::factory()->create()->id], 'live campaign'],
    'archived donor' => [fn () => ['donor_id' => tap(Donor::factory()->create())->delete()->id], 'archived'],
]);

it('does not let status or due dates be mass-assigned', function () {
    $plan = new RecurringPlan(['status' => 'cancelled', 'next_due_on' => '2030-01-01']);

    expect($plan->getAttributes())->not->toHaveKey('next_due_on')
        ->and($plan->status)->toBe(PledgeStatus::Active);
});

it('pauses, then resumes without owing the periods it was paused for', function () {
    $this->travelTo(CarbonImmutable::parse('2027-01-10 10:00', 'Asia/Karachi'));
    $plan = newPledge();

    pledges()->pause($plan, 'Travelling');
    expect($plan->fresh()?->status)->toBe(PledgeStatus::Paused);

    $this->travelTo(CarbonImmutable::parse('2027-04-02 10:00', 'Asia/Karachi'));
    $plan = pledges()->resume($plan);

    expect($plan)
        ->status->toBe(PledgeStatus::Active)
        ->paused_at->toBeNull()
        ->and($plan->next_due_on?->toDateString())->toBe('2027-04-10')
        ->and(Activity::query()->where('event', 'pledge_paused')->sole()->description)->toContain('Travelling');
});

it('cancels with a reason, for good', function () {
    $plan = newPledge();

    expect(fn () => pledges()->cancel($plan, ' '))->toThrow(DomainException::class, 'Give a reason');

    $plan = pledges()->cancel($plan, 'Donor asked by phone');
    expect($plan)->status->toBe(PledgeStatus::Cancelled)->next_due_on->toBeNull()->cancel_reason->toBe('Donor asked by phone');

    expect(fn () => pledges()->resume($plan))->toThrow(DomainException::class, 'cannot be resumed')
        ->and(fn () => pledges()->update($plan, ['amount' => '9000']))->toThrow(DomainException::class, 'cannot be changed');
});

it('completes a pledge once its end date has passed', function () {
    $this->travelTo(CarbonImmutable::parse('2027-01-10 10:00', 'Asia/Karachi'));
    $plan = newPledge(['ends_on' => '2027-02-20']);

    $plan = pledges()->advance($plan); // → 10 Feb
    expect($plan->status)->toBe(PledgeStatus::Active);

    $plan = pledges()->advance($plan); // 10 Mar is after the end
    expect($plan)->status->toBe(PledgeStatus::Completed)->next_due_on->toBeNull();
});

it('changes only the amount, method, end date and notes', function () {
    $plan = newPledge();

    $plan = pledges()->update($plan, ['amount' => '7,500', 'notes' => 'Raised after Eid']);

    expect($plan)->amount->toBe('7500.00')->notes->toBe('Raised after Eid')->frequency->toBe(PledgeFrequency::Monthly);
});

it('knows when a pledge is overdue', function () {
    $plan = newPledge();
    $this->travel(2)->days();

    expect($plan->fresh()?->isOverdue())->toBeTrue()
        ->and(RecurringPlan::query()->dueBy(today())->pluck('id')->all())->toBe([$plan->id]);
});

it('moves pledges to the surviving donor when duplicates are merged', function () {
    $survivor = Donor::factory()->create(['name' => 'Ayesha Khan']);
    $duplicate = Donor::factory()->create(['name' => 'Ayesha Khan']);
    $plan = newPledge(['donor_id' => $duplicate->id]);

    app(DonorMergeService::class)->merge($duplicate, $survivor);

    expect($plan->fresh()?->donor_id)->toBe($survivor->id);
});

// ── admin ───────────────────────────────────────────────────────────────

it('lets each role open pledges as the donations matrix allows', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/recurring-plans')->assertStatus($status);
})->with([
    'super admin' => [Role::SuperAdmin, 200],
    'finance' => [Role::Finance, 200],
    'manager (view)' => [Role::Manager, 200],
    'auditor (view)' => [Role::Auditor, 200],
    'field officer' => [Role::FieldOfficer, 403],
]);

it('lets Finance set up a pledge for a donor', function () {
    $finance = staffUser(Role::Finance);
    $donor = Donor::factory()->create();
    $fund = Fund::factory()->create();
    $this->actingAs($finance);

    Livewire::test(CreateRecurringPlan::class)
        ->fillForm([
            'donor_id' => $donor->id,
            'fund_id' => $fund->id,
            'amount' => '3000',
            'frequency' => PledgeFrequency::Quarterly->value,
            'starts_on' => today()->addDay()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(RecurringPlan::sole())
        ->donor_id->toBe($donor->id)
        ->frequency->toBe(PledgeFrequency::Quarterly)
        ->created_by->toBe($finance->id);
});

it('pauses, resumes and cancels from the pledge page', function () {
    $plan = newPledge();
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(ViewRecurringPlan::class, ['record' => $plan->getRouteKey()])
        ->callAction('pause', data: ['reason' => 'Asked to pause'])
        ->assertNotified('Pledge paused');
    expect($plan->fresh()?->status)->toBe(PledgeStatus::Paused);

    Livewire::test(ViewRecurringPlan::class, ['record' => $plan->getRouteKey()])
        ->assertActionHidden('pause')
        ->callAction('resume')
        ->assertNotified('Pledge resumed');

    Livewire::test(ViewRecurringPlan::class, ['record' => $plan->getRouteKey()])
        ->callAction('cancel', data: ['reason' => 'No longer able to give'])
        ->assertNotified('Pledge cancelled');
    expect($plan->fresh()?->status)->toBe(PledgeStatus::Cancelled);
});

it('hides pledge actions from view-only roles', function () {
    $plan = newPledge();
    $this->actingAs(staffUser(Role::Manager));

    Livewire::test(ViewRecurringPlan::class, ['record' => $plan->getRouteKey()])
        ->assertActionHidden('pause')
        ->assertActionHidden('cancel')
        ->assertActionHidden('edit');
});

it('edits the amount from the admin', function () {
    $plan = newPledge();
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(EditRecurringPlan::class, ['record' => $plan->getRouteKey()])
        ->fillForm(['amount' => '6000'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($plan->fresh()?->amount)->toBe('6000.00');
});

it('lists overdue pledges', function () {
    $overdue = newPledge();
    $this->travel(3)->days();
    $later = newPledge(['starts_on' => today()->addWeek()->toDateString()]);
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(ListRecurringPlans::class)
        ->assertCanSeeTableRecords([$overdue, $later])
        ->filterTable('overdue')
        ->assertCanSeeTableRecords([$overdue])
        ->assertCanNotSeeTableRecords([$later]);
});
