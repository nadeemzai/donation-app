<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\PaymentMethod;
use App\Mail\DonorLoginCode;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\User;
use App\Services\DonationService;
use App\Services\ReceiptService;
use App\Services\TaxCertificateService;
use App\Settings\NotificationSettings;
use App\Support\Cnic;
use App\Support\FiscalYear;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;

// Week 6 Day 4 — donor portal ("My donations"): one-time-code login, donations, receipts, certificates, details

beforeEach(fn () => Mail::fake());

function portalDonor(array $attributes = []): Donor
{
    return Donor::factory()->create(array_merge([
        'name' => 'Ayesha Khan',
        'phone' => '0300'.fake()->unique()->numerify('#######'),
        'email' => 'ayesha'.fake()->unique()->numerify('#####').'@example.com',
        'password' => null,
        'cnic' => null,
    ], $attributes));
}

function portalPaid(Donor $donor, string $amount = '5000.00'): Donation
{
    $service = app(DonationService::class);
    $by = User::factory()->create();

    return $service->confirm($service->create([
        'donor_id' => $donor->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => $amount,
        'channel' => DonationChannel::Admin,
        'payment_method' => PaymentMethod::Cash,
    ], $by), $by);
}

/** Ask for a code and return the one that was emailed. */
function portalEmailedCode(string $identifier): string
{
    test()->post('/my-donations/login', ['identifier' => $identifier])->assertRedirect('/my-donations/verify');

    $code = null;
    Mail::assertSent(DonorLoginCode::class, function (DonorLoginCode $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });

    return (string) $code;
}

function portalWhatsApp(): void
{
    $s = app(NotificationSettings::class);
    $s->whatsapp_enabled = true;
    $s->green_api_url = 'https://api.green-api.test';
    $s->green_api_id_instance = '1101000001';
    $s->green_api_token_instance = 'secret-token';
    $s->save();

    Http::fake(['api.green-api.test/*' => Http::response(['idMessage' => 'BAE5F4886F6F2D05'])]);
}

// ── logging in ──────────────────────────────────────────────────────────

it('sends a guest to the login page', function () {
    $this->get('/my-donations')->assertRedirect('/my-donations/login');
    $this->get('/my-donations/login')->assertOk()->assertSee('Send me a code');
});

it('shows My donations in the site header', function () {
    $this->get('/')->assertSee(route('donor.dashboard'), false)->assertSee('My donations');
});

it('logs a donor in with a code sent to their email', function () {
    $donor = portalDonor();
    $code = portalEmailedCode(strtoupper($donor->email));

    expect($code)->toMatch('/^\d{6}$/');
    Mail::assertSent(DonorLoginCode::class, fn (DonorLoginCode $m): bool => $m->hasTo((string) $donor->email));

    $this->get('/my-donations/verify')->assertOk()->assertSee(substr((string) $donor->email, 0, 1).'•••@example.com');
    $this->post('/my-donations/verify', ['code' => $code])->assertRedirect(route('donor.dashboard'));

    $this->assertAuthenticatedAs($donor, 'donor');
    expect($donor->fresh())
        ->last_login_at->not->toBeNull()
        ->email_verified_at->not->toBeNull()
        ->and(Activity::query()->where('event', 'donor_login')->count())->toBe(1);
});

it('sends the code on WhatsApp for a mobile number', function () {
    portalWhatsApp();
    $donor = portalDonor(['phone' => '03001234567']);

    $this->post('/my-donations/login', ['identifier' => '0300-1234567'])->assertRedirect('/my-donations/verify');

    Mail::assertNothingSent();
    $sent = Http::recorded()[0][0];
    expect($sent['chatId'])->toBe('923001234567@c.us')->and($sent['message'])->toContain('login code');

    $this->post('/my-donations/verify', ['code' => substr((string) $sent['message'], 0, 6)])->assertRedirect();
    $this->assertAuthenticatedAs($donor, 'donor');
    expect($donor->fresh()?->phone_verified_at)->not->toBeNull();
});

it('emails the code when WhatsApp is not set up', function () {
    $donor = portalDonor(['phone' => '03001234567']);

    portalEmailedCode('03001234567');

    Mail::assertSent(DonorLoginCode::class, fn (DonorLoginCode $m): bool => $m->hasTo((string) $donor->email));
});

it('answers the same way for someone who is not a donor', function () {
    $this->post('/my-donations/login', ['identifier' => 'nobody@example.com'])->assertRedirect('/my-donations/verify');

    Mail::assertNothingSent();
    $this->post('/my-donations/verify', ['code' => '123456'])->assertSessionHasErrors('code');
    $this->assertGuest('donor');
});

it('refuses anything that is not a mobile number or an email', function () {
    $this->from('/my-donations/login')->post('/my-donations/login', ['identifier' => '12345'])
        ->assertRedirect('/my-donations/login')
        ->assertSessionHasErrors('identifier');
});

it('rejects a wrong code and kills the code after five wrong tries', function () {
    $donor = portalDonor();
    $code = portalEmailedCode((string) $donor->email);
    $wrong = $code === '111111' ? '222222' : '111111';

    foreach (range(1, 5) as $try) {
        $this->post('/my-donations/verify', ['code' => $wrong])->assertSessionHasErrors('code');
    }

    $this->post('/my-donations/verify', ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest('donor');
});

it('uses a code only once', function () {
    $donor = portalDonor();
    $code = portalEmailedCode((string) $donor->email);

    $this->post('/my-donations/verify', ['code' => $code]);
    $this->post('/my-donations/logout');
    $this->withSession(['donor_login' => $donor->email])->post('/my-donations/verify', ['code' => $code])->assertSessionHasErrors('code');
});

it('lets a code expire after ten minutes', function () {
    $donor = portalDonor();
    $code = portalEmailedCode((string) $donor->email);

    $this->travel(11)->minutes();

    $this->post('/my-donations/verify', ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest('donor');
});

it('limits how many codes can be asked for', function () {
    $donor = portalDonor();

    foreach (range(1, 3) as $try) {
        $this->post('/my-donations/login', ['identifier' => $donor->email])->assertRedirect('/my-donations/verify');
    }

    $this->from('/my-donations/login')->post('/my-donations/login', ['identifier' => $donor->email])
        ->assertSessionHasErrors('identifier');
    Mail::assertSentCount(3);
});

it('logs a merged duplicate into the surviving record', function () {
    $survivor = portalDonor();
    $duplicate = portalDonor(['email' => 'old-address@example.com']);
    $duplicate->forceFill(['merged_into_id' => $survivor->id, 'merged_at' => now()])->save();
    $duplicate->delete();

    $code = portalEmailedCode('old-address@example.com');
    $this->post('/my-donations/verify', ['code' => $code]);

    $this->assertAuthenticatedAs($survivor, 'donor');
});

it('sends nothing to an archived donor', function () {
    $donor = portalDonor();
    $donor->delete();

    $this->post('/my-donations/login', ['identifier' => $donor->email])->assertRedirect('/my-donations/verify');

    Mail::assertNothingSent();
});

it('logs out without touching anything else', function () {
    $this->actingAs(portalDonor(), 'donor');

    $this->post('/my-donations/logout')->assertRedirect('/');
    $this->assertGuest('donor');
});

// ── donations, receipts, certificates ───────────────────────────────────

it('shows only the donor\'s own donations, with totals', function () {
    $donor = portalDonor();
    $mine = portalPaid($donor, '5000.00');
    portalPaid($donor, '2500.00');
    $other = portalPaid(portalDonor(), '9999.00');

    $this->actingAs($donor, 'donor')->get('/my-donations')
        ->assertOk()
        ->assertSee($mine->donation_no)
        ->assertDontSee($other->donation_no)
        ->assertSee('Rs 7,500.00')
        ->assertSee('Receipt');
});

it('downloads the donor\'s own receipt', function () {
    $donor = portalDonor();
    $receipt = portalPaid($donor)->receipt()->sole();

    $this->actingAs($donor, 'donor')
        ->get(route('donor.receipts.download', $receipt))
        ->assertOk()
        ->assertDownload($receipt->receipt_no.'.pdf');
});

it('never shows someone else\'s receipt or a voided one', function () {
    $donor = portalDonor();
    $theirs = portalPaid(portalDonor())->receipt()->sole();
    $voided = app(ReceiptService::class)->void(portalPaid($donor)->receipt()->sole(), 'Wrong amount');

    $this->actingAs($donor, 'donor');
    $this->get(route('donor.receipts.download', $theirs))->assertNotFound();
    $this->get(route('donor.receipts.download', $voided))->assertNotFound();
});

it('lists and downloads the donor\'s tax certificates', function () {
    $donor = portalDonor(['cnic' => '35202-1234567-1']);
    portalPaid($donor);
    $certificate = app(TaxCertificateService::class)->generate($donor, FiscalYear::containing(now()));

    $this->actingAs($donor, 'donor')->get('/my-donations')->assertSee($certificate->certificate_no);
    $this->get(route('donor.certificates.download', $certificate))->assertDownload($certificate->fileName());

    $this->actingAs(portalDonor(), 'donor')->get(route('donor.certificates.download', $certificate))->assertNotFound();
});

// ── my details ──────────────────────────────────────────────────────────

it('saves preferences and adds a CNIC once', function () {
    $donor = portalDonor();
    $this->actingAs($donor, 'donor');

    $this->put('/my-donations/profile', [
        'city' => 'Lahore',
        'language' => 'ur',
        'whatsapp_opt_in' => '1',
        'email_opt_in' => '0',
        'cnic' => '35202 1234567 1',
    ])->assertRedirect('/my-donations/profile')->assertSessionHasNoErrors();

    $fresh = $donor->fresh();
    expect($fresh?->city)->toBe('Lahore')
        ->and($fresh?->prefersUrdu())->toBeTrue()
        ->and($fresh?->wantsWhatsApp())->toBeTrue()
        ->and($fresh?->acceptsEmail())->toBeFalse()
        ->and($fresh?->cnic)->toBe('35202-1234567-1')
        ->and($fresh?->cnic_hash)->toBe(Cnic::hash('3520212345671'));
});

it('does not let a donor change a CNIC already on record', function () {
    $donor = portalDonor(['cnic' => '35202-1234567-1']);
    $this->actingAs($donor, 'donor');

    $this->from('/my-donations/profile')
        ->put('/my-donations/profile', ['language' => 'en', 'cnic' => '42101-7654321-9'])
        ->assertSessionHasErrors('cnic');

    expect($donor->fresh()?->cnic)->toBe('35202-1234567-1');
});

it('does not accept a CNIC that belongs to another donor', function () {
    portalDonor(['cnic' => '35202-1234567-1']);
    $donor = portalDonor();
    $this->actingAs($donor, 'donor');

    $this->from('/my-donations/profile')
        ->put('/my-donations/profile', ['language' => 'en', 'cnic' => '35202-1234567-1'])
        ->assertSessionHasErrors('cnic');
});

it('keeps donor pages away from staff who are not logged in as a donor', function () {
    $this->actingAs(User::factory()->create())->get('/my-donations')->assertRedirect('/my-donations/login');
});
