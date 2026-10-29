<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\FundType;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Filament\Resources\Banners\Pages\ListBanners;
use App\Filament\Resources\Funds\Pages\EditFund;
use App\Models\Banner;
use App\Models\Campaign;
use App\Models\CampaignUpdate;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\User;
use App\Services\DonationService;
use App\Support\SafeHtml;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// Week 6 Day 1 — public website: home, banners, campaigns, ways to give

beforeEach(fn () => Storage::fake('public'));

function bannerWithImage(array $attributes = []): Banner
{
    $banner = Banner::factory()->create($attributes);
    $banner->addMedia(UploadedFile::fake()->image('banner.jpg', 1920, 700))->toMediaCollection(Banner::IMAGE);

    return $banner;
}

function publicDonation(Campaign $campaign, string $amount, bool $anonymous = false, string $name = 'Imran Khan'): void
{
    $service = app(DonationService::class);
    $by = User::factory()->create();
    $donation = $service->create([
        'donor_id' => Donor::factory()->create(['name' => $name])->id,
        'fund_id' => null,
        'campaign_id' => $campaign->id,
        'amount' => $amount,
        'is_anonymous' => $anonymous,
        'channel' => DonationChannel::Admin,
        'payment_method' => PaymentMethod::Cash,
    ], $by);
    $service->confirm($donation, $by);
}

// ── home ────────────────────────────────────────────────────────────────

it('shows the Imdaad home page with menu, numbers and live campaigns', function () {
    $live = Campaign::factory()->live()->create(['title' => 'Winter Relief Lahore']);
    Campaign::factory()->create(['title' => 'Secret Draft Appeal']);
    publicDonation($live, '2500.00');

    $this->get('/')
        ->assertOk()
        ->assertSee('Imdaad')
        ->assertSee('Campaigns')
        ->assertSee('Ways to give')
        ->assertSee('Winter Relief Lahore')
        ->assertDontSee('Secret Draft Appeal')
        ->assertSee('2,500.00');
});

it('shows a built-in hero when no banners are set up', function () {
    $this->get('/')->assertOk()->assertSee('Give with trust. Every rupee receipted.');
});

it('shows only active banners inside their dates, in order', function () {
    bannerWithImage(['title' => 'Second banner', 'sort_order' => 2]);
    bannerWithImage(['title' => 'First banner', 'sort_order' => 1]);
    bannerWithImage(['title' => 'Switched off banner', 'is_active' => false]);
    bannerWithImage(['title' => 'Future banner', 'starts_at' => now()->addDay()]);
    bannerWithImage(['title' => 'Expired banner', 'ends_at' => now()->subDay()]);

    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['First banner', 'Second banner'])
        ->assertDontSee('Switched off banner')
        ->assertDontSee('Future banner')
        ->assertDontSee('Expired banner')
        ->assertDontSee('Give with trust. Every rupee receipted.');
});

// ── campaigns ───────────────────────────────────────────────────────────

it('lists live campaigns and filters them by fund', function () {
    $zakat = Fund::factory()->zakat()->create();
    Campaign::factory()->live()->create(['title' => 'Zakat for Widows', 'fund_id' => $zakat->id]);
    Campaign::factory()->live()->create(['title' => 'Clean Water Wells']);
    Campaign::factory()->closed()->create(['title' => 'Old Flood Appeal']);

    $this->get('/campaigns')->assertOk()->assertSee('Zakat for Widows')->assertSee('Clean Water Wells')->assertDontSee('Old Flood Appeal');
    $this->get('/campaigns?fund='.strtolower($zakat->code))->assertOk()->assertSee('Zakat for Widows')->assertDontSee('Clean Water Wells');
});

it('shows a campaign with its progress, updates and supporters — anonymous donors stay anonymous (BR-08)', function () {
    $campaign = Campaign::factory()->live()->create(['title' => 'School Fees Support', 'goal_amount' => '100000.00']);
    publicDonation($campaign, '20000.00', name: 'Ayesha Siddiqui');
    publicDonation($campaign, '5000.00', anonymous: true, name: 'Hidden Person');
    CampaignUpdate::factory()->create(['campaign_id' => $campaign->id, 'title' => '50 children enrolled', 'published_at' => now()->subDay()]);

    $this->get("/campaigns/{$campaign->slug}")
        ->assertOk()
        ->assertSee('School Fees Support')
        ->assertSee('Rs 25,000.00')
        ->assertSee('25% funded')
        ->assertSee('50 children enrolled')
        ->assertSee('Ayesha Siddiqui')
        ->assertSee('Anonymous')
        ->assertDontSee('Hidden Person');
});

it('never shows a draft campaign', function () {
    $draft = Campaign::factory()->create();

    $this->get("/campaigns/{$draft->slug}")->assertNotFound();
});

it('keeps a closed campaign page, pointing donors to its fund', function () {
    $closed = Campaign::factory()->closed()->create();

    $this->get("/campaigns/{$closed->slug}")
        ->assertOk()
        ->assertSee('This campaign has closed')
        ->assertSee(route('funds.show', strtolower($closed->fund->code)), false);
});

it('cleans the campaign story before showing it', function () {
    $campaign = Campaign::factory()->live()->create([
        'story' => '<p onclick="steal()">Help <strong>now</strong></p><script>alert(1)</script><a href="javascript:alert(2)">bad</a><a href="https://example.org">good</a>',
    ]);

    $this->get("/campaigns/{$campaign->slug}")
        ->assertOk()
        ->assertSee('<strong>now</strong>', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('onclick', false)
        ->assertDontSee('javascript:alert(2)', false)
        ->assertSee('href="https://example.org" rel="noopener nofollow" target="_blank"', false);
});

it('keeps safe formatting and drops everything else', function () {
    expect(SafeHtml::clean('<h2>Why</h2><div><span style="color:red">Plain</span></div><img src=x onerror=alert(1)><iframe src="x"></iframe>'))
        ->toBe('<h2>Why</h2>Plain')
        ->and(SafeHtml::clean(null))->toBe('')
        ->and(SafeHtml::clean('<p>اردو متن</p>'))->toBe('<p>اردو متن</p>');
});

// ── ways to give ────────────────────────────────────────────────────────

it('lists the active funds and shows each with its campaigns', function () {
    $zakat = Fund::factory()->zakat()->create(['description' => 'For those entitled to Zakat.']);
    Fund::factory()->inactive()->create(['name' => 'Retired Fund']);
    Campaign::factory()->live()->create(['title' => 'Zakat Ration Drive', 'fund_id' => $zakat->id]);

    $this->get('/ways-to-give')->assertOk()->assertSee('Zakat')->assertDontSee('Retired Fund');
    $this->get('/ways-to-give/'.strtolower($zakat->code))
        ->assertOk()
        ->assertSee('For those entitled to Zakat.')
        ->assertSee('Zakat Ration Drive')
        ->assertSee('spent only on people who are eligible for Zakat');
});

it('does not show an inactive fund page', function () {
    $fund = Fund::factory()->inactive()->create();

    $this->get('/ways-to-give/'.strtolower($fund->code))->assertNotFound();
});

// ── banners in the admin ────────────────────────────────────────────────

it('lets the content team add a banner with an image', function () {
    $this->actingAs(staffUser(Role::CampaignManager));

    Livewire::test(CreateBanner::class)
        ->fillForm([
            'image' => UploadedFile::fake()->image('hero.jpg', 1920, 700),
            'title' => 'Ramadan is here',
            'subtitle' => 'Feed a family this Ramadan',
            'button_label' => 'Give a ration bag',
            'button_url' => '/campaigns/ramadan',
            'text_align' => 'center',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $banner = Banner::query()->sole();
    expect($banner->title)->toBe('Ramadan is here')
        ->and($banner->imageUrl())->not->toBeNull();
});

it('refuses an unsafe banner link', function () {
    $this->actingAs(staffUser(Role::CampaignManager));

    Livewire::test(CreateBanner::class)
        ->fillForm(['image' => UploadedFile::fake()->image('hero.jpg'), 'title' => 'X', 'button_label' => 'Go', 'button_url' => 'javascript:alert(1)'])
        ->call('create')
        ->assertHasFormErrors(['button_url']);
});

it('lets only content roles manage banners', function (Role $role, int $status) {
    $this->actingAs(staffUser($role))->get('/admin/banners')->assertStatus($status);
})->with([
    'campaign manager' => [Role::CampaignManager, 200],
    'manager' => [Role::Manager, 200],
    'finance' => [Role::Finance, 403],
]);

it('reorders banners by dragging', function () {
    $a = bannerWithImage(['sort_order' => 1]);
    $b = bannerWithImage(['sort_order' => 2]);
    $this->actingAs(staffUser(Role::CampaignManager));

    Livewire::test(ListBanners::class)->call('reorderTable', [(string) $b->id, (string) $a->id]);

    expect($b->fresh()->sort_order)->toBeLessThan($a->fresh()->sort_order);
});

// ── fund pictures ───────────────────────────────────────────────────────

it('gives every fund a picture for its type until a photo is uploaded', function (Closure $make, string $file) {
    expect($make()->imageUrl())->toEndWith("/images/funds/{$file}.svg");
})->with([
    'zakat' => [fn () => Fund::factory()->zakat()->create(), 'zakat'],
    'sadqa' => [fn () => Fund::factory()->sadqa()->create(), 'sadqa'],
    'general' => [fn () => Fund::factory()->create(), 'general'],
    'restricted' => [fn () => Fund::factory()->restricted()->create(), 'restricted'],
]);

it('ships an illustration for every fund type', function () {
    foreach (FundType::cases() as $type) {
        expect(public_path("images/funds/{$type->value}.svg"))->toBeFile();
    }
});

it('shows the uploaded fund photo on the website instead of the illustration', function () {
    $fund = Fund::factory()->create(['name' => 'Widows Support']);
    $fund->addMedia(UploadedFile::fake()->image('widows.jpg', 1200, 750))->toMediaCollection(Fund::IMAGE);

    expect($fund->fresh()?->imageUrl())->toContain('widows.jpg');

    $this->get('/ways-to-give')->assertOk()->assertSee('widows.jpg');
    $this->get('/ways-to-give/'.strtolower($fund->code))->assertOk()->assertSee('widows.jpg');
});

it('uses the fund picture for a campaign without its own cover', function () {
    $fund = Fund::factory()->zakat()->create();
    Campaign::factory()->live()->create(['fund_id' => $fund->id, 'title' => 'No Cover Appeal']);

    $this->get('/campaigns')->assertOk()->assertSee('/images/funds/zakat.svg', false);
});

it('lets staff upload a fund photo in the admin', function () {
    $fund = Fund::factory()->create();
    $this->actingAs(staffUser(Role::Finance));

    Livewire::test(EditFund::class, ['record' => $fund->getRouteKey()])
        ->fillForm(['image' => UploadedFile::fake()->image('orphans.jpg', 1200, 750)])
        ->call('save')
        ->assertHasNoFormErrors();

    // Filament stores uploads under a random file name, so check the media itself.
    $media = $fund->fresh()?->getFirstMedia(Fund::IMAGE);
    expect($media)->not->toBeNull()
        ->and($media?->disk)->toBe('public')
        ->and($media?->mime_type)->toBe('image/jpeg')
        ->and($fund->fresh()?->imageUrl())->not->toContain('images/funds/');
});
