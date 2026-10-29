<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\PaymentMethod;
use App\Http\Resources\V1\BannerResource;
use App\Http\Resources\V1\CampaignDetailResource;
use App\Http\Resources\V1\CampaignResource;
use App\Http\Resources\V1\DonationResource;
use App\Http\Resources\V1\DonorResource;
use App\Http\Resources\V1\FundResource;
use App\Http\Resources\V1\PageResource;
use App\Models\Banner;
use App\Models\Campaign;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\Page;
use App\Models\User;
use App\Services\DonationService;
use App\Support\ApiFormat;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;

// Week 7 Day 1 — API conventions: headers, errors, rate limits, pagination, value formats, resources

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1/_conv')->group(function () {
        Route::get('rule', fn () => throw new DomainException('This campaign has closed.'));
        Route::get('boom', fn () => throw new RuntimeException('SQLSTATE secret details'));
        Route::get('funds', fn () => ApiResponse::paginated(Fund::query()->orderBy('id')->paginate(2), FundResource::class));
        Route::get('limited', fn () => ApiResponse::success(['ok' => true]))->middleware('throttle:api-auth');
        Route::get('me', fn () => ApiResponse::success(['ok' => true]))->middleware('auth:sanctum');
    });
});

// ── headers & format ────────────────────────────────────────────────────

it('answers JSON with version and request id headers, even without an Accept header', function () {
    $response = $this->get('/api/v1/health')
        ->assertOk()
        ->assertHeader('X-API-Version', 'v1')
        ->assertHeader('Content-Type', 'application/json');

    expect($response->headers->get('X-Request-Id'))->toMatch('/^[0-9a-f-]{36}$/');
});

it('echoes the app\'s request id and ignores a bad one', function () {
    $this->getJson('/api/v1/health', ['X-Request-Id' => 'app-7f3a9c21'])->assertHeader('X-Request-Id', 'app-7f3a9c21');

    $bad = $this->getJson('/api/v1/health', ['X-Request-Id' => '<script>'])->headers->get('X-Request-Id');
    expect($bad)->not->toBe('<script>');
});

it('picks Urdu from Accept-Language', function () {
    $this->getJson('/api/v1/health', ['Accept-Language' => 'ur-PK,ur;q=0.9'])->assertHeader('Content-Language', 'ur');
    $this->getJson('/api/v1/health', ['Accept-Language' => 'fr'])->assertHeader('Content-Language', 'en');
});

it('sends a logged-out request a 401, not the website login page', function () {
    $this->get('/api/v1/_conv/me')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
});

// ── errors ──────────────────────────────────────────────────────────────

it('turns a business rule into a 422 with its message', function () {
    $this->getJson('/api/v1/_conv/rule')
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'This campaign has closed.')
        ->assertJsonPath('errors', null);
});

it('hides crash details behind a plain 500', function () {
    config(['app.debug' => false]);

    $this->getJson('/api/v1/_conv/boom')
        ->assertStatus(500)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Something went wrong on our side. Please try again.')
        ->assertDontSee('SQLSTATE');
});

it('answers a wrong method with 405 in the envelope', function () {
    $this->postJson('/api/v1/health')->assertStatus(405)->assertJsonPath('message', 'Method not allowed.');
});

it('rate-limits with 429, Retry-After and meta.retry_after', function () {
    foreach (range(1, 5) as $try) {
        $this->getJson('/api/v1/_conv/limited')->assertOk();
    }

    $response = $this->getJson('/api/v1/_conv/limited')
        ->assertStatus(429)
        ->assertJsonPath('success', false)
        ->assertHeader('Retry-After');

    expect($response->json('meta.retry_after'))->toBeInt()->toBeGreaterThan(0);
});

// ── pagination ──────────────────────────────────────────────────────────

it('pages lists with meta.pagination', function () {
    Fund::factory()->count(3)->create();
    $total = Fund::query()->count();

    $this->getJson('/api/v1/_conv/funds?page=1')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.pagination.current_page', 1)
        ->assertJsonPath('meta.pagination.per_page', 2)
        ->assertJsonPath('meta.pagination.total', $total)
        ->assertJsonPath('meta.pagination.has_more', true)
        ->assertJsonStructure(['data' => [['id', 'code', 'name', 'type' => ['value', 'label'], 'image_url']]]);
});

// ── value formats ───────────────────────────────────────────────────────

it('formats money, enums and times the same way everywhere', function () {
    expect(ApiFormat::money('1234567.5'))->toBe(['amount' => '1234567.50', 'currency' => 'PKR', 'formatted' => 'Rs 1,234,567.50'])
        ->and(ApiFormat::money(null))->toBeNull()
        ->and(ApiFormat::enum(DonationStatus::Paid))->toBe(['value' => 'paid', 'label' => 'Paid'])
        ->and(ApiFormat::enum(DonationChannel::Web))->toBe(['value' => 'web', 'label' => 'Website'])
        ->and(ApiFormat::time(CarbonImmutable::parse('2026-12-14 05:30:00', 'UTC')))->toBe('2026-12-14T10:30:00+05:00')
        ->and(ApiFormat::url('/campaigns'))->toBe(url('/campaigns'))
        ->and(ApiFormat::url('https://example.com/x'))->toBe('https://example.com/x');
});

// ── resources ───────────────────────────────────────────────────────────

it('shapes a fund without its balance', function () {
    $fund = Fund::factory()->zakat()->create();

    $data = FundResource::make($fund)->resolve();

    expect($data)->toHaveKeys(['id', 'code', 'name', 'type', 'is_zakat', 'image_url', 'web_url'])
        ->not->toHaveKey('balance')
        ->and($data['is_zakat'])->toBeTrue()
        ->and($data['type']['value'])->toBe('zakat');
});

it('shapes a campaign for lists and for its own screen', function () {
    $campaign = Campaign::factory()->live()->create(['goal_amount' => '100000.00', 'story' => '<p>Help</p><script>x</script>']);
    $campaign->load('fund');

    $list = CampaignResource::make($campaign)->resolve();
    $detail = CampaignDetailResource::make($campaign)->resolve();

    expect($list)
        ->toHaveKeys(['slug', 'title', 'goal', 'raised', 'progress_percent', 'is_open', 'fund', 'image_url', 'web_url'])
        ->not->toHaveKey('story_html')
        ->and($list['goal'])->toBe(['amount' => '100000.00', 'currency' => 'PKR', 'formatted' => 'Rs 100,000.00'])
        ->and($list['is_open'])->toBeTrue()
        ->and($detail['story_html'])->toContain('Help')->not->toContain('script')
        ->and($detail['gallery'])->toBe([]);
});

it('expands a banner link to a full URL', function () {
    $banner = Banner::factory()->create(['button_url' => '/campaigns']);

    expect(BannerResource::make($banner)->resolve()['button_url'])->toBe(url('/campaigns'));
});

it('shapes a content page with cleaned HTML', function () {
    $page = Page::query()->where('slug', 'about')->sole();

    expect(PageResource::make($page)->resolve())
        ->toMatchArray(['slug' => 'about', 'title' => 'About us', 'web_url' => url('/about')])
        ->toHaveKey('body_html');
});

it('shapes a donation for its donor without staff-only details', function () {
    $service = app(DonationService::class);
    $by = User::factory()->create();
    $donation = $service->confirm($service->create([
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => '2500.00',
        'channel' => DonationChannel::Admin,
        'payment_method' => PaymentMethod::Cash,
        'staff_note' => 'Paid at the office',
    ], $by), $by);
    $donation->load(['fund', 'campaign', 'receipt']);

    $data = DonationResource::make($donation)->resolve();

    expect($data)
        ->toHaveKeys(['number', 'status', 'amount', 'net', 'payment_method', 'fund', 'campaign', 'paid_at', 'receipt'])
        ->not->toHaveKey('staff_note')
        ->not->toHaveKey('recorded_by')
        ->not->toHaveKey('id')
        ->and($data['status']['value'])->toBe('paid')
        ->and($data['net']['amount'])->toBe('2500.00')
        ->and($data['receipt']['number'])->toStartWith('RC-');
});

it('shows the donor only a masked CNIC', function () {
    $donor = Donor::factory()->create(['cnic' => '35202-1234567-1']);

    $data = DonorResource::make($donor)->resolve();

    expect($data['cnic_masked'])->toBe('35202-•••••••-1')
        ->and($data['has_cnic'])->toBeTrue()
        ->and(json_encode($data))->not->toContain('1234567');
});
