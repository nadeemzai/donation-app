<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\PaymentMethod;
use App\Enums\PledgeFrequency;
use App\Enums\Role;
use App\Filament\Widgets\CollectionsByFund;
use App\Filament\Widgets\CollectionsByMethod;
use App\Filament\Widgets\CollectionsOverview;
use App\Filament\Widgets\CollectionsTrendChart;
use App\Filament\Widgets\TopCampaigns;
use App\Filament\Widgets\WorkQueues;
use App\Models\Campaign;
use App\Models\ContactMessage;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\User;
use App\Payments\GatewayRegistry;
use App\Services\DashboardStats;
use App\Services\DonationService;
use App\Services\PaymentService;
use App\Services\RecurringPlanService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FakeGateway;

// Week 8 Day 4 — dashboard: collections, by fund, by method, top campaigns, trend, queues

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2027-01-15 12:00', 'Asia/Karachi'));
});

function dash(): DashboardStats
{
    return app(DashboardStats::class);
}

/** A donation confirmed at a given time (Pakistan). */
function dashPaid(string $amount, string $at, ?Fund $fund = null, PaymentMethod $method = PaymentMethod::Cash, ?Campaign $campaign = null): Donation
{
    $service = app(DonationService::class);
    $by = User::factory()->create();

    $donation = $service->create([
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => $campaign !== null ? null : ($fund ?? Fund::factory()->create())->id,
        'campaign_id' => $campaign?->id,
        'amount' => $amount,
        'channel' => DonationChannel::Admin,
        'payment_method' => $method,
        'reference' => 'REF-'.fake()->unique()->numerify('#####'),
    ], $by);

    return $service->confirm($donation, $by, null, CarbonImmutable::parse($at, 'Asia/Karachi'));
}

// ── numbers ─────────────────────────────────────────────────────────────

it('adds up money received today, this month and this tax year, net of refunds', function () {
    dashPaid('5000.00', '2027-01-15 09:00');
    $partly = dashPaid('2500.00', '2027-01-15 10:00');
    dashPaid('1000.00', '2027-01-03 10:00');
    dashPaid('4000.00', '2026-12-20 10:00');
    dashPaid('9000.00', '2026-06-30 22:00'); // last tax year
    $full = dashPaid('7000.00', '2027-01-15 11:00');
    app(DonationService::class)->refund($partly, '500.00', 'Duplicate', PaymentMethod::Cash);
    app(DonationService::class)->refund($full, '7000.00', 'Mistake', PaymentMethod::Cash);

    expect(dash()->today())->toBe(['amount' => '7000.00', 'count' => 2])
        ->and(dash()->thisMonth())->toBe(['amount' => '8000.00', 'count' => 3])
        ->and(dash()->thisTaxYear())->toBe(['amount' => '12000.00', 'count' => 4]);
});

it('compares with the same days of last month', function () {
    dashPaid('3000.00', '2026-12-10 10:00');
    dashPaid('8000.00', '2026-12-20 10:00'); // after the 15th: not counted

    expect(dash()->lastMonthSoFar())->toBe(['amount' => '3000.00', 'count' => 1]);
});

it('never counts pending money (BR-03)', function () {
    app(DonationService::class)->create([
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => '5000.00',
        'channel' => DonationChannel::Web,
        'payment_method' => PaymentMethod::BankTransfer,
    ]);

    expect(dash()->today())->toBe(['amount' => '0.00', 'count' => 0]);
});

it('splits this month by fund, listing funds with nothing too', function () {
    $zakat = Fund::factory()->zakat()->create();
    $sadqa = Fund::factory()->sadqa()->create(['name' => 'Sadqa']);
    $quiet = Fund::factory()->create(['name' => 'Quiet fund']);
    dashPaid('5000.00', '2027-01-10 10:00', $zakat);
    dashPaid('2000.00', '2027-01-11 10:00', $zakat);
    dashPaid('3000.00', '2027-01-12 10:00', $sadqa);

    $rows = collect(dash()->byFund(today()->startOfMonth(), today()->endOfDay()))->keyBy('code');

    expect($rows[$zakat->code])->toMatchArray(['amount' => '7000.00', 'count' => 2, 'is_zakat' => true, 'balance' => '7000.00'])
        ->and($rows[$sadqa->code]['amount'])->toBe('3000.00')
        ->and($rows[$quiet->code]['amount'])->toBe('0.00')
        ->and(dash()->byFund(today()->startOfMonth(), today()->endOfDay())[0]['code'])->toBe($zakat->code);
});

it('splits this month by payment method with shares', function () {
    dashPaid('6000.00', '2027-01-10 10:00', method: PaymentMethod::BankTransfer);
    dashPaid('3000.00', '2027-01-11 10:00', method: PaymentMethod::Cash);
    dashPaid('1000.00', '2027-01-12 10:00', method: PaymentMethod::Cheque);

    $rows = dash()->byMethod(today()->startOfMonth(), today()->endOfDay());

    expect(array_map(fn (array $r): string => $r['method']->value.' '.$r['amount'].' '.$r['share'], $rows))->toBe([
        'bank_transfer 6000.00 60',
        'cash 3000.00 30',
        'cheque 1000.00 10',
    ]);
});

it('ranks the campaigns that raised most this month', function () {
    $small = Campaign::factory()->live()->create(['title' => 'Small']);
    $big = Campaign::factory()->live()->create(['title' => 'Big']);
    dashPaid('1000.00', '2027-01-10 10:00', campaign: $small);
    dashPaid('9000.00', '2027-01-11 10:00', campaign: $big);
    dashPaid('9999.00', '2026-12-11 10:00', campaign: $small); // last month

    $rows = dash()->topCampaigns(today()->startOfMonth(), today()->endOfDay());

    expect(array_map(fn (array $r): string => $r['campaign']->title.' '.$r['amount'], $rows))->toBe(['Big 9000.00', 'Small 1000.00']);
});

it('gives a 30-day series ending today', function () {
    dashPaid('5000.00', '2027-01-15 09:00');
    dashPaid('2000.00', '2027-01-01 09:00');

    $daily = dash()->daily(30);

    expect($daily)->toHaveCount(30)
        ->and(array_key_last($daily))->toBe('2027-01-15')
        ->and($daily['2027-01-15'])->toBe('5000.00')
        ->and($daily['2027-01-01'])->toBe('2000.00')
        ->and($daily['2027-01-02'])->toBe('0.00');
});

it('adds pledges up to a monthly amount', function () {
    $plans = app(RecurringPlanService::class);
    $donor = Donor::factory()->create();
    $fund = Fund::factory()->create();
    foreach ([['5000', 'monthly'], ['3000', 'quarterly'], ['12000', 'yearly']] as [$amount, $frequency]) {
        $plans->create(['donor_id' => $donor->id, 'fund_id' => $fund->id, 'amount' => $amount, 'frequency' => $frequency, 'starts_on' => today()->toDateString()]);
    }

    expect(dash()->pledgedPerMonth())->toBe('7000.00');
});

it('counts what is waiting for action', function () {
    // A bank transfer with its proof.
    $proof = app(DonationService::class)->create([
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => '5000.00',
        'channel' => DonationChannel::Web,
        'payment_method' => PaymentMethod::BankTransfer,
    ]);
    $proof->addMedia(UploadedFile::fake()->image('slip.jpg'))->toMediaCollection(Donation::PROOF);

    // A gateway payment that reported the wrong amount → needs review.
    app(GatewayRegistry::class)->register(new FakeGateway(PaymentMethod::JazzCash));
    $wallet = app(DonationService::class)->create([
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => '3000.00',
        'channel' => DonationChannel::Web,
        'payment_method' => PaymentMethod::JazzCash,
    ]);
    [$payment] = app(PaymentService::class)->initiate($wallet, 'https://example.test');
    app(PaymentService::class)->applyResult($payment, FakeGateway::success($payment, '1.00'));

    // An overdue pledge, and an unanswered message.
    app(RecurringPlanService::class)->create(['donor_id' => Donor::factory()->create()->id, 'fund_id' => Fund::factory()->create()->id, 'amount' => '1000', 'frequency' => PledgeFrequency::Monthly->value, 'starts_on' => today()->toDateString()]);
    $this->travel(2)->days();
    ContactMessage::factory()->create();

    expect(dash()->queues())->toMatchArray([
        'proofs' => 1,
        'payments_review' => 1,
        'pledges_overdue' => 1,
        'messages' => 1,
        'pending_old' => 1, // the wallet donation, untouched for two days
    ]);
});

// ── widgets ─────────────────────────────────────────────────────────────

it('shows the collection numbers to roles that see donations', function () {
    dashPaid('5000.00', '2027-01-15 09:00');
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(CollectionsOverview::class)
        ->assertSee('Received today')
        ->assertSee('Rs 5,000.00')
        ->assertSee('Tax year 2026-27')
        ->assertSee('Pledged each month');

    Livewire::test(CollectionsByFund::class)->assertSee('This month by fund')->assertSee('Balance now');
    Livewire::test(CollectionsByMethod::class)->assertSee('Cash')->assertSee('(100%)');
    Livewire::test(TopCampaigns::class)->assertSee('No campaign donations yet this month');

    expect((new CollectionsTrendChart)->chartData()['labels'])->toHaveCount(30);
});

it('shows each role only the queues it can open', function () {
    $this->actingAs(staffUser(Role::Finance));
    Livewire::test(WorkQueues::class)
        ->assertSee('Transfer proofs to check')
        ->assertSee('Overdue pledges')
        ->assertDontSee('New website messages');

    $this->actingAs(staffUser(Role::CampaignManager));
    Livewire::test(WorkQueues::class)
        ->assertSee('New website messages')
        ->assertDontSee('Transfer proofs to check');
});

it('hides money widgets from roles without donation access', function () {
    $this->actingAs(staffUser(Role::FieldOfficer));

    expect(CollectionsOverview::canView())->toBeFalse()
        ->and(CollectionsByFund::canView())->toBeFalse()
        ->and(CollectionsTrendChart::canView())->toBeFalse()
        ->and(WorkQueues::canView())->toBeFalse();

    // Campaign Manager sees donations but not the ledger: no fund balances.
    $this->actingAs(staffUser(Role::CampaignManager));
    Livewire::test(CollectionsByFund::class)->assertSee('This month by fund')->assertDontSee('Balance now');
});

it('opens the dashboard for every role', function (Role $role) {
    $this->actingAs(staffUser($role))->get('/admin')->assertOk();
})->with(Role::cases());
