<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\MessageChannel;
use App\Enums\PaymentMethod;
use App\Enums\PledgeFrequency;
use App\Enums\PledgePeriodStatus;
use App\Enums\Role;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\RecurringPlan;
use App\Models\RecurringPlanPeriod;
use App\Models\User;
use App\Services\DonationService;
use App\Services\PledgeReminderService;
use App\Services\RecurringPlanService;
use App\Settings\GatewaySettings;
use App\Support\PledgeToken;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Week 8 Day 2 — pledge reminders with a pay link; periods marked paid when the donation is confirmed

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2027-01-10 09:10', 'Asia/Karachi'));

    $bank = app(GatewaySettings::class);
    $bank->bank_enabled = true;
    $bank->bank_iban = 'PK36MEZN0000001123456702';
    $bank->save();
});

function reminders(): PledgeReminderService
{
    return app(PledgeReminderService::class);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function duePledge(array $overrides = []): RecurringPlan
{
    return app(RecurringPlanService::class)->create(array_merge([
        'donor_id' => Donor::factory()->create(['name' => 'Ayesha Khan', 'email' => 'ayesha'.fake()->unique()->numerify('####').'@example.com', 'phone' => '0300'.fake()->unique()->numerify('#######')])->id,
        'fund_id' => Fund::factory()->create(['name' => 'Zakat Fund'])->id,
        'amount' => '5000',
        'frequency' => PledgeFrequency::Monthly->value,
        'starts_on' => today()->toDateString(),
    ], $overrides));
}

/**
 * @return Collection<int, MessageLog>
 */
function reminderEmails(): Collection
{
    return MessageLog::query()->where('template', MessageTemplate::PLEDGE_REMINDER)->where('channel', MessageChannel::Email->value)->get();
}

// ── reminders ───────────────────────────────────────────────────────────

it('reminds on the due date with a pay link and moves the pledge on', function () {
    $plan = duePledge();

    $stats = reminders()->run();

    $period = RecurringPlanPeriod::sole();
    expect($stats)->toMatchArray(['opened' => 1, 'reminded' => 1])
        ->and($period)->due_on->toDateString()->toBe('2027-01-10')->status->toBe(PledgePeriodStatus::Due)->amount->toBe('5000.00')->reminder_count->toBe(1)
        ->and($plan->fresh()?->next_due_on?->toDateString())->toBe('2027-02-10');

    $email = reminderEmails()->sole();
    expect($email->donor_id)->toBe($plan->donor_id)
        ->and($email->subject_id)->toBe($period->id)
        ->and($email->email_subject)->toBe('Your pledge of Rs 5,000.00 is due on 10 Jan 2027')
        ->and($email->body)->toContain('Zakat Fund')->toContain('/donate?')->toContain('pledge='.$period->id)->toContain('signature=');
});

it('does nothing before the due date, or for paused and cancelled pledges', function () {
    duePledge(['starts_on' => today()->addDay()->toDateString()]);
    app(RecurringPlanService::class)->pause(duePledge());
    app(RecurringPlanService::class)->cancel(duePledge(), 'Moved abroad');

    expect(reminders()->run())->toMatchArray(['opened' => 0, 'reminded' => 0])
        ->and(RecurringPlanPeriod::query()->count())->toBe(0);
});

it('is safe to run twice on the same day', function () {
    duePledge();

    reminders()->run();
    reminders()->run();

    expect(RecurringPlanPeriod::query()->count())->toBe(1)
        ->and(reminderEmails())->toHaveCount(1);
});

it('catches up after days without a run, reminding only the latest due date', function () {
    $plan = duePledge();
    $this->travelTo(CarbonImmutable::parse('2027-03-12 09:10', 'Asia/Karachi'));

    reminders()->run();

    $periods = RecurringPlanPeriod::query()->orderBy('due_on')->get();
    expect($periods->map(fn ($p) => $p->due_on->toDateString().' '.$p->status->value)->all())->toBe([
        '2027-01-10 missed',
        '2027-02-10 missed',
        '2027-03-10 due',
    ])
        ->and(reminderEmails())->toHaveCount(1)
        ->and($plan->fresh()?->next_due_on?->toDateString())->toBe('2027-04-10');
});

it('marks last month missed when this month comes round unpaid', function () {
    duePledge();
    reminders()->run();

    $this->travelTo(CarbonImmutable::parse('2027-02-10 09:10', 'Asia/Karachi'));
    reminders()->run();

    expect(RecurringPlanPeriod::query()->orderBy('due_on')->pluck('status')->map->value->all())->toBe(['missed', 'due']);
});

it('sends one more reminder three days later, and no more', function () {
    duePledge();
    reminders()->run();

    $this->travel(2)->days();
    expect(reminders()->run()['second_reminders'])->toBe(0);

    $this->travel(1)->days();
    expect(reminders()->run()['second_reminders'])->toBe(1);

    $this->travel(5)->days();
    expect(reminders()->run()['second_reminders'])->toBe(0)
        ->and(RecurringPlanPeriod::sole()->reminder_count)->toBe(2)
        ->and(reminderEmails())->toHaveCount(2);
});

it('sends nothing when the template is switched off', function () {
    MessageTemplate::forKey(MessageTemplate::PLEDGE_REMINDER)->update(['is_active' => false]);
    duePledge();

    expect(reminders()->run())->toMatchArray(['opened' => 1, 'reminded' => 0])
        ->and(MessageLog::query()->count())->toBe(0);
});

it('runs from the command line and every morning at 09:10 Pakistan time', function () {
    duePledge();

    $this->artisan('pledges:remind')->expectsOutputToContain('Pledges due: 1')->assertSuccessful();

    $this->artisan('schedule:list')->assertSuccessful();
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'pledges:remind'));
    expect($event?->expression)->toBe('10 9 * * *')->and($event?->timezone)->toBe('Asia/Karachi');
});

// ── paying from the link ────────────────────────────────────────────────

it('opens the donate page ready to pay the pledge, without asking for details', function () {
    duePledge();
    reminders()->run();
    $period = RecurringPlanPeriod::sole();

    $this->get(PledgeReminderService::payUrl($period))
        ->assertOk()
        ->assertViewHas('pledge', fn (?RecurringPlanPeriod $p): bool => $p?->is($period) === true)
        ->assertViewHas('prefillAmount', '5000.00')
        ->assertSee('Your pledge of Rs 5,000.00 to Zakat Fund')
        ->assertSee('Giving as Ayesha Khan')
        ->assertDontSee('id="phone"', false);
});

it('refuses a tampered or expired pay link', function () {
    duePledge();
    reminders()->run();
    $period = RecurringPlanPeriod::sole();

    $this->get('/donate?pledge='.$period->id)->assertOk()->assertViewHas('pledge', fn ($p): bool => $p === null)->assertSee('This pay link has expired');

    $url = PledgeReminderService::payUrl($period);
    $this->travel(PledgeReminderService::PAY_LINK_DAYS + 2)->days();
    $this->get($url)->assertOk()->assertViewHas('pledge', fn ($p): bool => $p === null);
});

it('marks the period paid once the donation from the link is confirmed', function () {
    $plan = duePledge();
    reminders()->run();
    $period = RecurringPlanPeriod::sole();

    $this->post('/donate', [
        'fund_id' => $plan->fund_id,
        'amount' => '5000',
        'payment_method' => PaymentMethod::BankTransfer->value,
        'reference' => 'RAAST-1',
        'proof' => UploadedFile::fake()->image('slip.jpg'),
        'pledge_token' => PledgeToken::for($period),
        'idempotency_key' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors()->assertRedirectContains('/donate/thank-you/');

    $donation = Donation::sole();
    expect($donation)->donor_id->toBe($plan->donor_id)->recurring_plan_period_id->toBe($period->id)
        ->and($period->fresh()?->status)->toBe(PledgePeriodStatus::Due); // not paid until Finance confirms

    $by = User::factory()->create();
    app(DonationService::class)->confirm($donation, $by);

    expect($period->fresh())
        ->status->toBe(PledgePeriodStatus::Paid)
        ->donation_id->toBe($donation->id)
        ->paid_at->not->toBeNull();

    // A paid period gets no second reminder, and its link says thank you.
    $this->travel(4)->days();
    expect(reminders()->run()['second_reminders'])->toBe(0);
    $this->get(PledgeReminderService::payUrl($period))->assertSee('already been received');
});

it('accepts a late payment for a missed period', function () {
    $plan = duePledge();
    reminders()->run();
    $january = RecurringPlanPeriod::sole();
    $this->travelTo(CarbonImmutable::parse('2027-02-10 09:10', 'Asia/Karachi'));
    reminders()->run();
    expect($january->fresh()?->status)->toBe(PledgePeriodStatus::Missed);

    $service = app(DonationService::class);
    $by = User::factory()->create();
    $donation = $service->create([
        'donor_id' => $plan->donor_id,
        'fund_id' => $plan->fund_id,
        'recurring_plan_period_id' => $january->id,
        'amount' => '5000.00',
        'channel' => DonationChannel::Admin,
        'payment_method' => PaymentMethod::Cash,
    ], $by);
    $service->confirm($donation, $by);

    expect($january->fresh()?->status)->toBe(PledgePeriodStatus::Paid);
});

it('ignores a forged pledge token and asks for details like any donation', function () {
    $this->from('/donate')->post('/donate', [
        'fund_id' => Fund::factory()->create()->id,
        'amount' => '5000',
        'payment_method' => PaymentMethod::BankTransfer->value,
        'reference' => 'X',
        'proof' => UploadedFile::fake()->image('slip.jpg'),
        'pledge_token' => 'not-a-real-token',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertSessionHasErrors(['name', 'phone']);

    expect(Donation::query()->count())->toBe(0);
});

// ── admin & overdue ─────────────────────────────────────────────────────

it('shows each due date and whether it was paid on the pledge page', function () {
    $plan = duePledge();
    reminders()->run();

    $this->actingAs(staffUser(Role::Finance))
        ->get("/admin/recurring-plans/{$plan->id}")
        ->assertOk()
        ->assertSee('Payments by due date')
        ->assertSee('10 Jan 2027');
});

it('counts an unpaid reminded period as overdue', function () {
    $plan = duePledge();
    reminders()->run();
    expect($plan->fresh()?->isOverdue())->toBeFalse(); // due today

    $this->travel(1)->day();
    expect($plan->fresh()?->isOverdue())->toBeTrue();
});
