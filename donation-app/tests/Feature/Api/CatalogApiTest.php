<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Models\Banner;
use App\Models\Campaign;
use App\Models\CampaignUpdate;
use App\Models\Fund;
use App\Models\Page;
use App\Payments\GatewayRegistry;
use App\Settings\GatewaySettings;
use App\Settings\ZakatSettings;
use Tests\Support\FakeGateway;

// Week 7 Day 3 — app config, catalog (banners, funds, campaigns, updates), pages, Zakat

function catalogUpdate(Campaign $campaign, array $attributes = []): CampaignUpdate
{
    return CampaignUpdate::query()->forceCreate(array_merge([
        'campaign_id' => $campaign->id,
        'title' => 'Blankets delivered',
        'body' => '<p>200 families in Muzaffarabad received blankets.</p>',
        'published_at' => now()->subDay(),
    ], $attributes));
}

// ── app config ──────────────────────────────────────────────────────────

it('gives the app everything it needs at start-up', function () {
    app(GatewayRegistry::class)->register(new FakeGateway(PaymentMethod::JazzCash));
    $bank = app(GatewaySettings::class);
    $bank->bank_enabled = true;
    $bank->bank_iban = 'PK36MEZN0000001123456702';
    $bank->bank_account_title = 'Imdaad Welfare Trust';
    $bank->save();
    $rates = app(ZakatSettings::class);
    $rates->silver_rate_per_tola = 3000;
    $rates->save();

    $this->getJson('/api/v1/app/config')
        ->assertOk()
        ->assertJsonPath('data.organisation.name', 'Imdaad')
        ->assertJsonPath('data.donate.min.amount', '100.00')
        ->assertJsonPath('data.donate.presets.0.formatted', 'Rs 1,000.00')
        ->assertJsonPath('data.donate.methods.0.value', 'jazzcash')
        ->assertJsonPath('data.donate.methods.0.direct_wallet', true)
        ->assertJsonPath('data.donate.methods.1.value', 'bank_transfer')
        ->assertJsonPath('data.donate.methods.1.needs_proof', true)
        ->assertJsonPath('data.donate.bank.iban', 'PK36MEZN0000001123456702')
        ->assertJsonPath('data.zakat.nisab.amount', '157500.00')
        ->assertJsonPath('data.zakat.nisab_basis', 'silver')
        ->assertJsonPath('data.links.about', url('/about'))
        ->assertJsonPath('data.links.privacy', null) // still a draft
        ->assertJsonPath('data.login.code_length', 6)
        ->assertJsonStructure(['data' => ['app' => ['min_version', 'latest_version']]]);
});

it('hides bank details when bank transfer is switched off', function () {
    $this->getJson('/api/v1/app/config')->assertOk()->assertJsonPath('data.donate.bank', null)->assertJsonPath('data.donate.methods', []);
});

// ── banners & funds ─────────────────────────────────────────────────────

it('lists the banners showing now, in order', function () {
    Banner::factory()->create(['title' => 'Second', 'sort_order' => 2]);
    Banner::factory()->create(['title' => 'First', 'sort_order' => 1, 'button_url' => '/donate']);
    Banner::factory()->create(['title' => 'Hidden', 'is_active' => false]);

    $this->getJson('/api/v1/banners')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'First')
        ->assertJsonPath('data.0.button_url', url('/donate'));
});

it('lists active funds only', function () {
    $zakat = Fund::factory()->zakat()->create(['sort_order' => 0]);
    $closed = Fund::factory()->inactive()->create();

    $codes = collect($this->getJson('/api/v1/funds')->assertOk()->json('data'))->pluck('code');

    expect($codes)->toContain($zakat->code)->not->toContain($closed->code);
});

// ── campaigns ───────────────────────────────────────────────────────────

it('pages live campaigns, featured first, never drafts', function () {
    $featured = Campaign::factory()->live()->create(['is_featured' => true]);
    Campaign::factory()->live()->count(2)->create();
    $draft = Campaign::factory()->create();

    $response = $this->getJson('/api/v1/campaigns?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.slug', $featured->slug)
        ->assertJsonPath('meta.pagination.total', 3)
        ->assertJsonPath('meta.pagination.has_more', true);

    expect(collect($response->json('data'))->pluck('slug'))->not->toContain($draft->slug);
});

it('filters campaigns by fund, featured, search and closed', function () {
    $fund = Fund::factory()->create(['code' => 'WIDOWS']);
    $widows = Campaign::factory()->live()->for($fund)->create(['title' => 'Widow support Lahore']);
    $water = Campaign::factory()->live()->create(['title' => 'Clean water wells', 'is_featured' => true]);
    $closed = Campaign::factory()->closed()->create();

    $slugs = fn (string $query): array => collect($this->getJson('/api/v1/campaigns'.$query)->assertOk()->json('data'))->pluck('slug')->all();

    expect($slugs('?fund=widows'))->toBe([$widows->slug])
        ->and($slugs('?featured=1'))->toBe([$water->slug])
        ->and($slugs('?q=water'))->toBe([$water->slug])
        ->and($slugs('?status=closed'))->toBe([$closed->slug]);
});

it('caps the page size at 50', function () {
    $this->getJson('/api/v1/campaigns?per_page=500')->assertOk()->assertJsonPath('meta.pagination.per_page', 50);
});

it('shows one campaign with its story, and keeps closed ones reachable', function () {
    $live = Campaign::factory()->live()->create(['story' => '<p>Help us</p>']);
    $closed = Campaign::factory()->closed()->create();
    $draft = Campaign::factory()->create();

    $this->getJson("/api/v1/campaigns/{$live->slug}")
        ->assertOk()
        ->assertJsonPath('data.story_html', '<p>Help us</p>')
        ->assertJsonPath('data.is_open', true);

    $this->getJson("/api/v1/campaigns/{$closed->slug}")->assertOk()->assertJsonPath('data.is_open', false);
    $this->getJson("/api/v1/campaigns/{$draft->slug}")->assertNotFound()->assertJsonPath('message', 'Not found.');
    $this->getJson('/api/v1/campaigns/no-such-campaign')->assertNotFound();
});

it('lists a campaign\'s published updates, newest first', function () {
    $campaign = Campaign::factory()->live()->create();
    $old = catalogUpdate($campaign, ['title' => 'Week 1', 'published_at' => now()->subDays(5)]);
    $new = catalogUpdate($campaign, ['title' => 'Week 2', 'published_at' => now()->subDay()]);
    catalogUpdate($campaign, ['title' => 'Draft', 'published_at' => null]);
    catalogUpdate($campaign, ['title' => 'Scheduled', 'published_at' => now()->addDay()]);

    $this->getJson("/api/v1/campaigns/{$campaign->slug}/updates")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $new->id)
        ->assertJsonPath('data.1.id', $old->id)
        ->assertJsonPath('data.0.photos', []);
});

it('collects updates from every public campaign into the impact feed', function () {
    $live = Campaign::factory()->live()->create(['title' => 'Winter relief']);
    $draft = Campaign::factory()->create();
    catalogUpdate($live);
    catalogUpdate($draft, ['title' => 'Secret']);

    $this->getJson('/api/v1/impact-updates')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.campaign.title', 'Winter relief')
        ->assertDontSee('Secret');
});

// ── content & Zakat ─────────────────────────────────────────────────────

it('serves published pages only', function () {
    $this->getJson('/api/v1/pages/about')->assertOk()->assertJsonPath('data.title', 'About us');
    $this->getJson('/api/v1/pages/privacy')->assertNotFound(); // draft
    Page::factory()->create(['slug' => 'volunteer', 'title' => 'Volunteer']);
    $this->getJson('/api/v1/pages/volunteer')->assertOk()->assertJsonPath('data.has_contact_form', false);
});

it('calculates Zakat and says what to pre-fill on the donate screen', function () {
    $rates = app(ZakatSettings::class);
    $rates->gold_rate_per_tola = 300000;
    $rates->silver_rate_per_tola = 3000;
    $rates->save();
    $fund = Fund::query()->where('is_zakat', true)->first() ?? Fund::factory()->zakat()->create();

    $this->postJson('/api/v1/zakat/calculate', ['gold_tola' => '2', 'cash' => '2,00,000', 'bank' => '100000', 'debts' => '100000'])
        ->assertOk()
        ->assertJsonPath('data.is_due', true)
        ->assertJsonPath('data.zakat.amount', '20000.00')
        ->assertJsonPath('data.net_wealth.amount', '800000.00')
        ->assertJsonPath('data.nisab.amount', '157500.00')
        ->assertJsonPath('data.pay.fund_code', $fund->code)
        ->assertJsonPath('data.pay.amount.amount', '20000.00');

    $this->postJson('/api/v1/zakat/calculate', ['cash' => '50000'])
        ->assertOk()
        ->assertJsonPath('data.is_due', false)
        ->assertJsonPath('data.pay', null);
});

it('rejects Zakat figures that are not numbers', function () {
    $this->postJson('/api/v1/zakat/calculate', ['cash' => 'lots'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cash'], 'errors');
});
