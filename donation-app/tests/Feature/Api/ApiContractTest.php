<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\PaymentMethod;
use App\Models\Campaign;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\User;
use App\Payments\GatewayRegistry;
use App\Services\DonationService;
use App\Settings\GatewaySettings;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeGateway;

// Week 7 Day 5 — the contract with the mobile app. If a test here has to change, the app
// (and docs/api-conventions.md) must change too. Additions are fine; changes are not.

/** Every endpoint in v1. Removing or renaming one is a breaking change → /api/v2. */
const API_V1_ENDPOINTS = [
    'GET api/v1/health',
    'GET api/v1/app/config',
    'GET api/v1/banners',
    'GET api/v1/funds',
    'GET api/v1/campaigns',
    'GET api/v1/campaigns/{slug}',
    'GET api/v1/campaigns/{slug}/updates',
    'GET api/v1/impact-updates',
    'GET api/v1/pages/{slug}',
    'POST api/v1/zakat/calculate',
    'POST api/v1/auth/register',
    'POST api/v1/auth/login',
    'POST api/v1/auth/otp/send',
    'POST api/v1/auth/otp/verify',
    'POST api/v1/auth/logout',
    'POST api/v1/donations',
    'GET api/v1/donations/{number}/status',
    'POST api/v1/donations/{number}/proof',
    'GET api/v1/me',
    'PUT api/v1/me',
    'GET api/v1/me/donations',
    'GET api/v1/me/receipts/{receipt}/download',
    'GET api/v1/me/tax-certificates',
    'GET api/v1/me/tax-certificates/{certificate}/download',
    'POST api/v1/devices',
    'DELETE api/v1/devices',
];

const ENVELOPE = ['success', 'data', 'message', 'errors', 'meta'];
const MONEY = ['amount', 'currency', 'formatted'];
const ENUM = ['value', 'label'];

/**
 * @return list<LaravelRoute>
 */
function apiV1Routes(): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        fn (LaravelRoute $r): bool => str_starts_with($r->uri(), 'api/v1/'),
    ));
}

function endpointKey(LaravelRoute $route): string
{
    $method = collect($route->methods())->reject(fn (string $m): bool => $m === 'HEAD')->first();

    return $method.' '.$route->uri();
}

function needsToken(LaravelRoute $route): bool
{
    return in_array('auth:sanctum', $route->gatherMiddleware(), true);
}

function methodDoc(LaravelRoute $route): array
{
    [$class, $method] = str_contains($route->getActionName(), '@')
        ? explode('@', $route->getActionName())
        : [$route->getActionName(), '__invoke'];

    $reflection = new ReflectionMethod($class, $method);

    return [
        'class' => (string) $reflection->getDeclaringClass()->getDocComment(),
        'method' => (string) $reflection->getDocComment(),
    ];
}

// ── inventory & docs ────────────────────────────────────────────────────

it('has exactly the documented v1 endpoints', function () {
    $actual = collect(apiV1Routes())->map(fn (LaravelRoute $r): string => endpointKey($r))->sort()->values()->all();
    $expected = collect(API_V1_ENDPOINTS)->sort()->values()->all();

    expect($actual)->toBe($expected);
});

it('names every v1 route api.v1.*', function () {
    foreach (apiV1Routes() as $route) {
        expect($route->getName())->toStartWith('api.v1.');
    }
});

it('documents every endpoint for Scribe: a title, a group, and whether it needs a token', function () {
    $problems = [];

    foreach (apiV1Routes() as $route) {
        $doc = methodDoc($route);
        $key = endpointKey($route);
        $lines = collect(explode("\n", $doc['method']))
            ->map(fn (string $l): string => trim(ltrim(trim($l), '/*')))
            ->filter(fn (string $l): bool => $l !== '' && ! str_starts_with($l, '@'));

        if ($lines->isEmpty()) {
            $problems[] = "{$key}: no title in the docblock";
        }

        if (! str_contains($doc['class'].$doc['method'], '@group')) {
            $problems[] = "{$key}: no @group";
        }

        $authenticated = str_contains($doc['method'], '@authenticated') || str_contains($doc['class'], '@authenticated');
        $public = str_contains($doc['method'], '@unauthenticated');

        if (needsToken($route) && ! $authenticated) {
            $problems[] = "{$key}: needs a token but is not marked @authenticated";
        }

        if (! needsToken($route) && ! $public) {
            $problems[] = "{$key}: public but not marked @unauthenticated";
        }
    }

    expect($problems)->toBe([]);
});

it('gives every API form request examples for the docs', function () {
    $missing = collect(glob(app_path('Http/Requests/Api/V1/*.php')) ?: [])
        ->map(fn (string $file): string => 'App\\Http\\Requests\\Api\\V1\\'.basename($file, '.php'))
        ->reject(fn (string $class): bool => method_exists($class, 'bodyParameters'))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

// ── access ──────────────────────────────────────────────────────────────

it('answers every token endpoint with a 401 envelope when there is no token', function () {
    foreach (apiV1Routes() as $route) {
        if (! needsToken($route)) {
            continue;
        }

        $uri = preg_replace('/\{[^}]+\}/', '1', $route->uri());
        $method = collect($route->methods())->reject(fn (string $m): bool => $m === 'HEAD')->first();

        $this->json((string) $method, '/'.$uri)
            ->assertUnauthorized()
            ->assertJsonStructure(ENVELOPE)
            ->assertJsonPath('success', false);
    }
});

it('refuses the status and proof endpoints without a signed link', function () {
    $this->getJson('/api/v1/donations/DN-2026-000001/status')->assertForbidden()->assertJsonStructure(ENVELOPE);
    $this->postJson('/api/v1/donations/DN-2026-000001/proof')->assertForbidden()->assertJsonStructure(ENVELOPE);
});

it('wraps every public read in the envelope with the version header', function () {
    $campaign = Campaign::factory()->live()->create();

    foreach (['/api/v1/health', '/api/v1/app/config', '/api/v1/banners', '/api/v1/funds', '/api/v1/campaigns', "/api/v1/campaigns/{$campaign->slug}", "/api/v1/campaigns/{$campaign->slug}/updates", '/api/v1/impact-updates', '/api/v1/pages/about'] as $uri) {
        $this->getJson($uri)
            ->assertOk()
            ->assertJsonStructure(ENVELOPE)
            ->assertJsonPath('success', true)
            ->assertHeader('X-API-Version', 'v1');
    }
});

// ── shapes the app relies on ────────────────────────────────────────────

it('keeps the catalog shapes', function () {
    $campaign = Campaign::factory()->live()->create(['goal_amount' => '100000.00']);

    $this->getJson('/api/v1/funds')->assertJsonStructure(['data' => [['id', 'code', 'name', 'type' => ENUM, 'is_zakat', 'description', 'restriction', 'image_url', 'web_url']]]);

    $this->getJson('/api/v1/campaigns')->assertJsonStructure([
        'data' => [['id', 'slug', 'title', 'summary', 'status' => ENUM, 'is_featured', 'is_open', 'goal' => MONEY, 'raised' => MONEY, 'progress_percent', 'donor_count', 'starts_on', 'ends_on', 'image_url', 'fund' => ['id', 'code', 'name', 'is_zakat'], 'web_url']],
        'meta' => ['pagination' => ['current_page', 'per_page', 'total', 'last_page', 'has_more']],
    ]);

    $this->getJson("/api/v1/campaigns/{$campaign->slug}")->assertJsonStructure(['data' => ['slug', 'story_html', 'gallery', 'goal' => MONEY]]);

    $this->getJson('/api/v1/app/config')->assertJsonStructure(['data' => [
        'organisation' => ['name', 'phone', 'email', 'address', 'logo_url'],
        'donate' => ['currency', 'min' => MONEY, 'max' => MONEY, 'presets', 'methods', 'bank'],
        'zakat' => ['gold_rate_per_tola', 'silver_rate_per_tola', 'rates_date', 'nisab_basis', 'nisab', 'nisab_tola', 'rate'],
        'login' => ['code_length', 'code_expires_in'],
        'links' => ['about', 'contact', 'privacy', 'donor_portal'],
        'app' => ['min_version', 'latest_version', 'android_url', 'ios_url'],
        'languages',
    ]]);
});

it('keeps the donate and status shapes', function () {
    app(GatewayRegistry::class)->register(new FakeGateway(PaymentMethod::JazzCash));
    $fund = Fund::factory()->create();

    $start = $this->postJson('/api/v1/donations', [
        'fund_code' => $fund->code,
        'amount' => '5000',
        'payment_method' => 'jazzcash',
        'name' => 'Ayesha Khan',
        'phone' => '03001234567',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated()->assertJsonStructure(['data' => [
        'donation' => ['number', 'status' => ENUM, 'amount' => MONEY, 'refunded' => MONEY, 'net' => MONEY, 'payment_method' => ENUM, 'channel' => ENUM, 'fund', 'campaign', 'is_anonymous', 'dedication', 'created_at', 'paid_at', 'receipt'],
        'next_step',
        'payment' => ['reference', 'status' => ENUM, 'message'],
        'checkout' => ['type', 'url', 'fields', 'message'],
        'status_url',
        'proof_url',
        'bank',
    ]]);

    expect($start->json('data.next_step'))->toBeIn(['wait', 'open_checkout', 'upload_proof', 'done', 'closed']);

    $this->getJson((string) $start->json('data.status_url'))->assertOk()->assertJsonStructure(['data' => [
        'donation' => ['number', 'status' => ENUM],
        'payment',
        'next_step',
        'is_final',
        'poll_after_seconds',
    ]]);
});

it('keeps the bank transfer shape', function () {
    $bank = app(GatewaySettings::class);
    $bank->bank_enabled = true;
    $bank->bank_iban = 'PK36MEZN0000001123456702';
    $bank->save();

    $this->postJson('/api/v1/donations', [
        'fund_code' => Fund::factory()->create()->code,
        'amount' => '5000',
        'payment_method' => 'bank_transfer',
        'name' => 'Ayesha Khan',
        'phone' => '03001234567',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated()
        ->assertJsonPath('data.next_step', 'upload_proof')
        ->assertJsonStructure(['data' => ['proof_url', 'bank' => ['bank_name', 'account_title', 'iban', 'raast_id', 'amount' => MONEY, 'reference_hint']]]);
});

it('keeps the donor shapes', function () {
    $donor = Donor::factory()->create();
    $service = app(DonationService::class);
    $by = User::factory()->create();
    $service->confirm($service->create([
        'donor_id' => $donor->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => '2500.00',
        'channel' => DonationChannel::Admin,
        'payment_method' => PaymentMethod::Cash,
    ], $by), $by);
    Sanctum::actingAs($donor);

    $this->getJson('/api/v1/me')->assertOk()->assertJsonStructure(['data' => [
        'id', 'donor_code', 'name', 'type' => ENUM, 'phone', 'email', 'city', 'cnic_masked', 'has_cnic',
        'phone_verified', 'email_verified', 'preferences' => ['language', 'whatsapp_opt_in', 'email_opt_in'], 'member_since',
        'totals' => ['given' => MONEY, 'this_tax_year' => MONEY, 'tax_year', 'donations'],
    ]]);

    $this->getJson('/api/v1/me/donations')->assertOk()->assertJsonStructure([
        'data' => [['number', 'status' => ENUM, 'amount' => MONEY, 'net' => MONEY, 'receipt' => ['id', 'number', 'download_url']]],
        'meta' => ['pagination'],
    ]);
});

it('keeps the error shapes', function () {
    $this->postJson('/api/v1/auth/login', [])
        ->assertStatus(422)
        ->assertJsonStructure(ENVELOPE)
        ->assertJsonPath('data', null)
        ->assertJsonStructure(['errors' => ['identifier']]);

    $this->getJson('/api/v1/campaigns/no-such-campaign')
        ->assertNotFound()
        ->assertExactJson(['success' => false, 'data' => null, 'message' => 'Not found.', 'errors' => null, 'meta' => []]);
});
