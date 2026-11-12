<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Mail\ContactMessageReceived;
use App\Models\Campaign;
use App\Models\ContactMessage;
use App\Models\Fund;
use App\Models\Page;
use App\Settings\GeneralSettings;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

// Week 6 Day 5 — admin-edited pages, contact form, sitemap, robots.txt, SEO tags

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function contactForm(array $overrides = []): array
{
    return array_merge([
        'name' => 'Imran Siddiqui',
        'email' => 'imran@example.com',
        'phone' => '',
        'subject' => 'Receipt question',
        'message' => 'I donated last week but did not get my receipt. Can you help?',
    ], $overrides);
}

// ── pages ───────────────────────────────────────────────────────────────

it('starts with About us and Contact pages in the menu', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(route('pages.show', 'about'), false)
        ->assertSee(route('pages.show', 'contact'), false)
        ->assertSee('About us');

    $this->get('/about')->assertOk()->assertSee('Who we are');
    $this->get('/contact')->assertOk()->assertSee('Send us a message');
});

it('keeps draft pages and unknown addresses private', function () {
    $this->get('/privacy')->assertNotFound(); // seeded as a draft
    $this->get('/no-such-page')->assertNotFound();
});

it('drops the menu link when About is unpublished', function () {
    Page::query()->where('slug', 'about')->update(['is_published' => false]);

    $this->get('/')->assertOk()->assertDontSee(route('pages.show', 'about'), false);
});

it('lists footer pages in the footer', function () {
    Page::factory()->create(['slug' => 'annual-report', 'title' => 'Annual report 2026', 'show_in_footer' => true]);

    $this->get('/')->assertSee('Annual report 2026');
});

it('never lets a page take over an address the app uses', function () {
    $this->get('/admin')->assertRedirect(); // still the admin login, not a page
    $this->get('/zakat')->assertOk()->assertSee('Zakat calculator');
});

it('cleans unsafe HTML out of a page', function () {
    Page::factory()->create(['slug' => 'our-team', 'body' => '<p>Hello</p><script>alert(1)</script><a href="javascript:alert(2)">x</a>']);

    $this->get('/our-team')->assertOk()->assertSee('Hello')->assertDontSee('<script>alert(1)', false)->assertDontSee('javascript:', false);
});

it('uses the page summary for search engines', function () {
    $this->get('/about')
        ->assertSee('<meta name="description" content="Who we are, what we do and how we look after every rupee you give.">', false)
        ->assertSee('<link rel="canonical" href="'.url('/about').'">', false);
});

// ── contact form ────────────────────────────────────────────────────────

it('saves a contact message and copies it to the organisation inbox', function () {
    Mail::fake();
    $settings = app(GeneralSettings::class);
    $settings->email = 'info@imdaad.org';
    $settings->save();

    $this->from('/contact')->post('/contact-message', contactForm())
        ->assertRedirect('/contact')
        ->assertSessionHas('contact_sent', true);

    $message = ContactMessage::sole();
    expect($message)->name->toBe('Imran Siddiqui')->email->toBe('imran@example.com')->handled_at->toBeNull();
    Mail::assertQueued(ContactMessageReceived::class, fn (ContactMessageReceived $m): bool => $m->hasTo('info@imdaad.org') && $m->contact->is($message));

    $this->get('/contact')->assertSee('your message has reached us');
});

it('accepts a mobile number instead of an email', function () {
    $this->post('/contact-message', contactForm(['email' => '', 'phone' => '0300 1234567']))->assertSessionHasNoErrors();

    expect(ContactMessage::sole()->phone)->toBe('03001234567');
});

it('needs a way to reply and a real message', function (array $form, string $field) {
    $this->from('/contact')->post('/contact-message', contactForm($form))->assertSessionHasErrors($field);

    expect(ContactMessage::query()->count())->toBe(0);
})->with([
    'no email or phone' => [['email' => '', 'phone' => ''], 'email'],
    'bad phone' => [['email' => '', 'phone' => '12345'], 'phone'],
    'message too short' => [['message' => 'hi'], 'message'],
]);

it('quietly drops messages from bots', function () {
    $this->post('/contact-message', contactForm(['website' => 'http://spam.example']))->assertSessionHas('contact_sent', true);

    expect(ContactMessage::query()->count())->toBe(0);
});

// ── admin ───────────────────────────────────────────────────────────────

it('lets website staff mark a message handled', function () {
    $message = ContactMessage::factory()->create();
    $manager = staffUser(Role::Manager);
    $this->actingAs($manager);

    Livewire::test(ListContactMessages::class)
        ->assertCanSeeTableRecords([$message])
        ->callAction(TestAction::make('handle')->table($message), data: ['staff_note' => 'Called back, receipt re-sent'])
        ->assertNotified('Marked handled');

    expect($message->fresh())
        ->handled_at->not->toBeNull()
        ->handled_by->toBe($manager->id)
        ->staff_note->toBe('Called back, receipt re-sent');
});

it('creates a page with its own address', function () {
    $this->actingAs(staffUser(Role::CampaignManager));

    Livewire::test(CreatePage::class)
        ->fillForm(['title' => 'Volunteer with us', 'slug' => 'volunteer', 'body' => '<p>Join us</p>', 'is_published' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->get('/volunteer')->assertOk()->assertSee('Join us');
});

it('refuses addresses the website already uses', function (string $slug) {
    $this->actingAs(staffUser(Role::SuperAdmin));

    Livewire::test(CreatePage::class)
        ->fillForm(['title' => 'Test', 'slug' => $slug])
        ->call('create')
        ->assertHasFormErrors(['slug']);
})->with(['donate', 'admin', 'contact', 'Has Spaces', 'UPPER']);

it('does not let anyone delete About or Contact', function () {
    $about = Page::query()->where('slug', 'about')->sole();
    $other = Page::factory()->create();
    $this->actingAs(staffUser(Role::SuperAdmin));

    Livewire::test(ListPages::class)
        ->assertActionHidden(TestAction::make('delete')->table($about))
        ->assertActionVisible(TestAction::make('delete')->table($other));
});

// ── search engines ──────────────────────────────────────────────────────

it('lists public pages in the sitemap and nothing private', function () {
    $live = Campaign::factory()->live()->create();
    $draft = Campaign::factory()->create();
    $fund = Fund::factory()->create();
    $hidden = Page::factory()->draft()->create(['slug' => 'secret-plans']);

    $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $response->assertSee(route('campaigns.show', $live->slug), false)
        ->assertSee(route('funds.show', strtolower($fund->code)), false)
        ->assertSee(route('pages.show', 'about'), false)
        ->assertSee(route('zakat'), false)
        ->assertDontSee('<loc>'.route('campaigns.show', (string) $draft->slug).'</loc>', false)
        ->assertDontSee('<loc>'.route('pages.show', $hidden->slug).'</loc>', false)
        ->assertDontSee('/my-donations', false);
});

it('blocks search engines on a test site', function () {
    $this->get('/robots.txt')->assertOk()->assertSee("User-agent: *\nDisallow: /", false)->assertDontSee('Sitemap:');
});

it('points search engines to the sitemap in production', function () {
    app()['env'] = 'production';

    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Disallow: /admin')
        ->assertSee('Disallow: /my-donations')
        ->assertSee('Sitemap: '.route('sitemap'));
});

it('describes the organisation to search engines on the home page', function () {
    $this->get('/')->assertSee('"@type":"NGO"', false)->assertSee('<meta property="og:url"', false);
    $this->get('/campaigns')->assertDontSee('"@type":"NGO"', false);
});
