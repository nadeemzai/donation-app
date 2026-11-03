<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\PaymentMethod;
use App\Enums\PledgeFrequency;
use App\Enums\PledgePeriodStatus;
use App\Enums\PledgeStatus;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\RecurringPlan;
use App\Models\RecurringPlanPeriod;
use App\Payments\GatewayRegistry;
use App\Services\DonorPledgeService;
use App\Services\PaymentService;
use App\Services\PledgeReminderService;
use App\Services\RecurringPlanService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeGateway;

// Week 8 Day 3 — donors manage their own pledges in the portal and the app

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2027-01-10 10:00', 'Asia/Karachi'));
});

/**
 * @param  array<string, mixed>  $overrides
 */
function ownPledge(Donor $donor, array $overrides = []): RecurringPlan
{
    return app(RecurringPlanService::class)->create(array_merge([
        'donor_id' => $donor->id,
        'fund_id' => Fund::factory()->create(['name' => 'Zakat Fund'])->id,
        'amount' => '5000',
        'frequency' => PledgeFrequency::Monthly->value,
        'starts_on' => today()->toDateString(),
    ], $overrides));
}

// ── portal ──────────────────────────────────────────────────────────────

it('keeps the pledges pages for logged-in donors', function () {
    $this->get('/my-donations/pledges')->assertRedirect('/my-donations/login');
});

it('lists the donor\'s pledges with a pay-now link for an unpaid due date', function () {
    $donor = Donor::factory()->create();
    $plan = ownPledge($donor);
    app(PledgeReminderService::class)->run();
    ownPledge(Donor::factory()->create(), ['amount' => '9999']); // someone else's

    $this->actingAs($donor, 'donor')->get('/my-donations/pledges')
        ->assertOk()
        ->assertSee('Rs 5,000.00 · Every month')
        ->assertSee('Zakat Fund')
        ->assertSee('Pay now')
        ->assertSee('pledge='.RecurringPlanPeriod::sole()->id, false)
        ->assertDontSee('9,999');
});

it('lets a donor set up a pledge to a fund or a campaign', function () {
    $donor = Donor::factory()->create();
    $fund = Fund::factory()->create(['code' => 'SADQA']);
    $campaign = Campaign::factory()->live()->create();
    $this->actingAs($donor, 'donor');

    $this->get('/my-donations/pledges/new')->assertOk()->assertSee($campaign->title);

    $this->post('/my-donations/pledges', ['cause' => 'fund:sadqa', 'amount' => '2,500', 'frequency' => 'monthly', 'starts_on' => '2027-01-15', 'payment_method' => ''])
        ->assertRedirect('/my-donations/pledges')
        ->assertSessionHas('status');
    $this->post('/my-donations/pledges', ['cause' => 'campaign:'.$campaign->slug, 'amount' => '1000', 'frequency' => 'quarterly', 'starts_on' => '2027-01-10']);

    $plans = RecurringPlan::query()->where('donor_id', $donor->id)->orderBy('id')->get();
    expect($plans)->toHaveCount(2)
        ->and($plans[0])->fund_id->toBe($fund->id)->amount->toBe('2500.00')->channel->toBe(DonationChannel::Web)->created_by->toBeNull()
        ->and($plans[1])->campaign_id->toBe($campaign->id)->frequency->toBe(PledgeFrequency::Quarterly);
});

it('refuses a pledge that starts in the past or has no cause', function () {
    $this->actingAs(Donor::factory()->create(), 'donor');

    $this->from('/my-donations/pledges/new')
        ->post('/my-donations/pledges', ['cause' => 'fund:NOPE', 'amount' => '1000', 'frequency' => 'monthly', 'starts_on' => '2027-01-09'])
        ->assertSessionHasErrors(['starts_on']);

    $this->from('/my-donations/pledges/new')
        ->post('/my-donations/pledges', ['cause' => 'fund:NOPE', 'amount' => '1000', 'frequency' => 'monthly', 'starts_on' => '2027-01-10'])
        ->assertSessionHasErrors(['cause']);

    expect(RecurringPlan::query()->count())->toBe(0);
});

it('stops at ten running pledges', function () {
    $donor = Donor::factory()->create();
    foreach (range(1, DonorPledgeService::MAX_ACTIVE) as $i) {
        ownPledge($donor);
    }
    $this->actingAs($donor, 'donor');

    $this->from('/my-donations/pledges/new')
        ->post('/my-donations/pledges', ['cause' => 'fund:'.Fund::factory()->create()->code, 'amount' => '1000', 'frequency' => 'monthly', 'starts_on' => '2027-01-10'])
        ->assertSessionHasErrors(['form']);
});

it('lets a donor pause, resume, change and cancel their pledge', function () {
    $donor = Donor::factory()->create();
    $plan = ownPledge($donor);
    $this->actingAs($donor, 'donor');

    $this->patch("/my-donations/pledges/{$plan->id}", ['action' => 'pause'])->assertRedirect('/my-donations/pledges');
    expect($plan->fresh()?->status)->toBe(PledgeStatus::Paused);

    $this->patch("/my-donations/pledges/{$plan->id}", ['action' => 'resume']);
    expect($plan->fresh()?->status)->toBe(PledgeStatus::Active);

    $this->patch("/my-donations/pledges/{$plan->id}", ['amount' => '7,000']);
    expect($plan->fresh()?->amount)->toBe('7000.00');

    $this->patch("/my-donations/pledges/{$plan->id}", ['action' => 'cancel', 'reason' => 'Moving abroad']);
    expect($plan->fresh())->status->toBe(PledgeStatus::Cancelled)->cancel_reason->toBe('Cancelled by the donor: Moving abroad');
});

it('never lets a donor touch someone else\'s pledge', function () {
    $plan = ownPledge(Donor::factory()->create());
    $this->actingAs(Donor::factory()->create(), 'donor');

    $this->patch("/my-donations/pledges/{$plan->id}", ['action' => 'cancel'])->assertNotFound();
    expect($plan->fresh()?->status)->toBe(PledgeStatus::Active);
});

// ── API ─────────────────────────────────────────────────────────────────

it('lists the donor\'s pledges in the app', function () {
    $donor = Donor::factory()->create();
    ownPledge($donor);
    app(PledgeReminderService::class)->run();
    Sanctum::actingAs($donor);

    $this->getJson('/api/v1/me/recurring-plans')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.amount.amount', '5000.00')
        ->assertJsonPath('data.0.frequency.value', 'monthly')
        ->assertJsonPath('data.0.next_due_on', '2027-02-10')
        ->assertJsonPath('data.0.payments.due', 1)
        ->assertJsonPath('data.0.pay_now.pledge_period_id', RecurringPlanPeriod::sole()->id)
        ->assertJsonPath('data.0.pay_now.amount.amount', '5000.00');
});

it('sets up a pledge from the app', function () {
    $donor = Donor::factory()->create();
    $fund = Fund::factory()->create();
    Sanctum::actingAs($donor);

    $this->postJson('/api/v1/me/recurring-plans', ['fund_code' => strtolower($fund->code), 'amount' => '3,000', 'frequency' => 'yearly', 'starts_on' => '2027-02-01'])
        ->assertCreated()
        ->assertJsonPath('data.status.value', 'active')
        ->assertJsonPath('data.frequency.value', 'yearly')
        ->assertJsonPath('data.next_due_on', '2027-02-01')
        ->assertJsonPath('data.pay_now', null);

    expect(RecurringPlan::sole())->donor_id->toBe($donor->id)->channel->toBe(DonationChannel::Mobile);
});

it('validates a pledge from the app', function () {
    Sanctum::actingAs(Donor::factory()->create());

    $this->postJson('/api/v1/me/recurring-plans', ['amount' => 'lots', 'frequency' => 'weekly'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['fund_code', 'amount', 'frequency'], 'errors');

    $this->postJson('/api/v1/me/recurring-plans', ['fund_code' => 'NOPE', 'amount' => '1000', 'frequency' => 'monthly'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['fund_code'], 'errors');

    $this->postJson('/api/v1/me/recurring-plans', ['fund_code' => Fund::factory()->create()->code, 'amount' => '50', 'frequency' => 'monthly'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'The smallest pledge is Rs 100.');
});

it('pauses, changes and cancels a pledge from the app', function () {
    $donor = Donor::factory()->create();
    $plan = ownPledge($donor);
    Sanctum::actingAs($donor);

    $this->patchJson("/api/v1/me/recurring-plans/{$plan->id}", ['action' => 'pause'])->assertOk()->assertJsonPath('data.status.value', 'paused');
    $this->patchJson("/api/v1/me/recurring-plans/{$plan->id}", ['amount' => '6000'])->assertOk()->assertJsonPath('data.amount.amount', '6000.00');
    $this->patchJson("/api/v1/me/recurring-plans/{$plan->id}", ['action' => 'cancel'])->assertOk()->assertJsonPath('data.status.value', 'cancelled');
    $this->patchJson("/api/v1/me/recurring-plans/{$plan->id}", ['action' => 'resume'])->assertStatus(422);
    $this->patchJson("/api/v1/me/recurring-plans/{$plan->id}", [])->assertStatus(422)->assertJsonValidationErrors(['action'], 'errors');
});

it('hides other donors\' pledges from the app', function () {
    $plan = ownPledge(Donor::factory()->create());
    Sanctum::actingAs(Donor::factory()->create());

    $this->getJson("/api/v1/me/recurring-plans/{$plan->id}")->assertNotFound();
    $this->patchJson("/api/v1/me/recurring-plans/{$plan->id}", ['action' => 'cancel'])->assertNotFound();
});

it('pays a pledge due date from the app and marks it paid', function () {
    app(GatewayRegistry::class)->register(new FakeGateway(PaymentMethod::JazzCash));
    $donor = Donor::factory()->create(['phone' => '03001234567']);
    $plan = ownPledge($donor);
    app(PledgeReminderService::class)->run();
    $period = RecurringPlanPeriod::sole();
    Sanctum::actingAs($donor);

    $this->postJson('/api/v1/donations', [
        'fund_code' => $plan->fund->code,
        'amount' => '5000',
        'payment_method' => 'jazzcash',
        'pledge_period_id' => $period->id,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();

    $donation = Donation::sole();
    expect($donation->recurring_plan_period_id)->toBe($period->id);

    app(PaymentService::class)->applyResult($donation->payments()->sole(), FakeGateway::success($donation->payments()->sole()));

    expect($period->fresh()?->status)->toBe(PledgePeriodStatus::Paid);
});

it('refuses to pay someone else\'s pledge due date', function () {
    app(GatewayRegistry::class)->register(new FakeGateway(PaymentMethod::JazzCash));
    ownPledge(Donor::factory()->create());
    app(PledgeReminderService::class)->run();
    Sanctum::actingAs(Donor::factory()->create(['phone' => '03001234567']));

    $this->postJson('/api/v1/donations', [
        'fund_code' => Fund::factory()->create()->code,
        'amount' => '5000',
        'payment_method' => 'jazzcash',
        'pledge_period_id' => RecurringPlanPeriod::sole()->id,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertStatus(422)->assertJsonValidationErrors(['pledge_period_id'], 'errors');
});
