<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Resources\ProofVerifications\ProofVerificationResource;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\Payment;
use App\Payments\GatewayRegistry;
use App\Settings\GatewaySettings;
use App\Support\Cnic;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\FakeGateway;

// Week 6 Day 2 — public donate wizard (FR-DON-01, FR-PAY-01/03)

beforeEach(function () {
    Storage::fake('local');

    $bank = app(GatewaySettings::class);
    $bank->bank_enabled = true;
    $bank->bank_name = 'Meezan Bank';
    $bank->bank_account_title = 'Imdaad Welfare Trust';
    $bank->bank_iban = 'PK36MEZN0000001123456702';
    $bank->save();
});

/** JazzCash, replaced by the pretend gateway. */
function donateGateway(): FakeGateway
{
    $gateway = new FakeGateway(PaymentMethod::JazzCash);
    app(GatewayRegistry::class)->register($gateway);

    return $gateway;
}

/**
 * A filled-in donate form.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function donateForm(array $overrides = []): array
{
    return array_merge([
        'fund_id' => $overrides['fund_id'] ?? Fund::factory()->create()->id,
        'amount' => '5,000',
        'name' => 'Ayesha Khan',
        'phone' => '0300-1234567',
        'email' => 'ayesha@example.com',
        'payment_method' => PaymentMethod::JazzCash->value,
        'whatsapp_opt_in' => '1',
        'idempotency_key' => (string) Str::uuid(),
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function bankForm(array $overrides = []): array
{
    return donateForm(array_merge([
        'payment_method' => PaymentMethod::BankTransfer->value,
        'reference' => 'RAAST-88120044',
        'proof' => UploadedFile::fake()->image('transfer.jpg'),
    ], $overrides));
}

// ── the page ────────────────────────────────────────────────────────────

it('shows the funds and the ways to pay', function () {
    donateGateway();
    Fund::factory()->create(['name' => 'Widows Support']);

    $this->get('/donate')
        ->assertOk()
        ->assertSee('Widows Support')
        ->assertSee('JazzCash')
        ->assertSee('Bank transfer / Raast')
        ->assertSee('PK36MEZN0000001123456702')
        ->assertDontSee('Pay from your Easypaisa account'); // not configured
});

it('preselects the fund from the link', function () {
    $fund = Fund::factory()->create();

    $this->get('/donate?fund='.strtolower($fund->code))->assertOk()->assertViewHas('selectedFund', $fund->id);
});

it('preselects a live campaign from the link', function () {
    $campaign = Campaign::factory()->live()->create();

    $this->get('/donate?campaign='.$campaign->slug)
        ->assertOk()
        ->assertSee($campaign->title)
        ->assertViewHas('campaign', fn (?Campaign $c): bool => $c?->is($campaign) === true)
        ->assertViewHas('selectedFund', $campaign->fund_id);
});

it('does not offer a closed campaign', function () {
    $campaign = Campaign::factory()->closed()->create();

    $this->get('/donate?campaign='.$campaign->slug)
        ->assertOk()
        ->assertViewHas('campaign', fn (?Campaign $c): bool => $c === null)
        ->assertSee('no longer taking donations');
});

it('says online giving is paused when no payment method is on', function () {
    $bank = app(GatewaySettings::class);
    $bank->bank_enabled = false;
    $bank->save();

    $this->get('/donate')->assertOk()->assertSee('Online giving is paused');
});

it('links the header Donate button to the wizard', function () {
    $this->get('/')->assertOk()->assertSee(route('donate'), false);
});

// ── paying by JazzCash / Easypaisa ──────────────────────────────────────

it('creates a guest donor and a pending donation, then sends the donor to the gateway', function () {
    $gateway = donateGateway();
    $form = donateForm();

    $response = $this->post('/donate', $form);

    $donation = Donation::sole();
    $payment = Payment::sole();
    $response->assertRedirect("https://fake-gateway.test/pay?ref={$payment->reference}");

    expect($donation)
        ->status->toBe(DonationStatus::Pending)
        ->channel->toBe(DonationChannel::Web)
        ->payment_method->toBe(PaymentMethod::JazzCash)
        ->amount->toBe('5000.00')
        ->fund_id->toBe($form['fund_id'])
        ->recorded_by->toBeNull()
        ->and($donation->donor)
        ->name->toBe('Ayesha Khan')
        ->phone->toBe('03001234567')
        ->email->toBe('ayesha@example.com')
        ->password->toBeNull()
        ->and($donation->donor->wantsWhatsApp())->toBeTrue()
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($gateway->initiated[0]['request']->mobileNumber)->toBe('03001234567')
        ->and($gateway->initiated[0]['request']->returnUrl)->toBe(route('payments.jazzcash.return'));
});

it('gives a campaign donation to the campaign and its fund', function () {
    donateGateway();
    $campaign = Campaign::factory()->live()->create();

    $this->post('/donate', donateForm(['fund_id' => null, 'campaign_id' => $campaign->id]))->assertRedirect();

    expect(Donation::sole())->campaign_id->toBe($campaign->id)->fund_id->toBe($campaign->fund_id);
});

it('cancels the donation and explains when the gateway cannot be reached', function () {
    $gateway = donateGateway();
    $gateway->failToInitiate = true;

    $this->from('/donate')->post('/donate', donateForm())
        ->assertRedirect('/donate')
        ->assertSessionHasErrors(['form' => 'We could not reach JazzCash just now. No money was taken. Please try again, or choose another way to pay.']);

    expect(Donation::sole()->status)->toBe(DonationStatus::Cancelled)
        ->and(session()->getOldInput('name'))->toBe('Ayesha Khan')
        ->and(session()->getOldInput('idempotency_key'))->toBeNull(); // the next try is a new donation
});

it('does not create a second donation when the form is sent twice', function () {
    donateGateway();
    $form = donateForm();

    $this->post('/donate', $form)->assertRedirect();
    $this->post('/donate', $form)->assertRedirect();

    expect(Donation::query()->count())->toBe(1)
        ->and(Donor::query()->count())->toBe(1);
});

// ── bank transfer / Raast ───────────────────────────────────────────────

it('takes a bank transfer with its proof and puts it in the proof queue', function () {
    $response = $this->post('/donate', bankForm());

    $donation = Donation::sole();
    $response->assertRedirectContains('/donate/thank-you/'.$donation->donation_no);

    expect($donation)
        ->status->toBe(DonationStatus::Pending)
        ->payment_method->toBe(PaymentMethod::BankTransfer)
        ->reference->toBe('RAAST-88120044')
        ->received_at->not->toBeNull()
        ->and($donation->getFirstMedia(Donation::PROOF)?->disk)->toBe('local')
        ->and(ProofVerificationResource::getEloquentQuery()->count())->toBe(1)
        ->and(Payment::query()->count())->toBe(0);

    $this->get((string) $response->headers->get('Location'))
        ->assertOk()
        ->assertSee($donation->donation_no)
        ->assertSee('We will check your transfer');
});

it('needs a transaction ID and a proof file for a bank transfer', function () {
    $this->from('/donate')
        ->post('/donate', bankForm(['reference' => '', 'proof' => null]))
        ->assertSessionHasErrors(['reference', 'proof']);

    expect(Donation::query()->count())->toBe(0);
});

it('attaches the proof only once when a bank transfer is sent twice', function () {
    $form = bankForm();

    $this->post('/donate', $form);
    $this->post('/donate', array_merge($form, ['proof' => UploadedFile::fake()->image('again.jpg')]));

    expect(Donation::query()->count())->toBe(1)
        ->and(Donation::sole()->getMedia(Donation::PROOF))->toHaveCount(1);
});

it('only opens the thank-you page from a signed link', function () {
    $this->post('/donate', bankForm());

    $this->get('/donate/thank-you/'.Donation::sole()->donation_no)->assertForbidden();
});

// ── donors ──────────────────────────────────────────────────────────────

it('uses the existing donor with that phone and never changes their record', function () {
    donateGateway();
    $donor = Donor::factory()->create(['name' => 'Bilal Ahmed', 'phone' => '03001234567', 'email' => 'bilal@example.com']);

    $this->post('/donate', donateForm(['name' => 'Someone Else', 'email' => 'someone@example.com', 'cnic' => '35202-1234567-1']))->assertRedirect();

    $donation = Donation::sole();
    $fresh = $donor->fresh();

    expect($donation->donor_id)->toBe($donor->id)
        ->and(Donor::query()->count())->toBe(1)
        ->and($fresh?->name)->toBe('Bilal Ahmed')
        ->and($fresh?->email)->toBe('bilal@example.com')
        ->and($fresh?->cnic)->toBeNull()
        ->and($donation->staff_note)
        ->toContain('Name given on the website: Someone Else')
        ->toContain('Email given on the website: someone@example.com')
        ->toContain('CNIC ending 5671');
});

it('finds an existing donor by email when the phone is new', function () {
    donateGateway();
    $donor = Donor::factory()->create(['email' => 'ayesha@example.com']);

    $this->post('/donate', donateForm())->assertRedirect();

    expect(Donation::sole()->donor_id)->toBe($donor->id)
        ->and($donor->fresh()?->phone)->toBe($donor->phone);
});

it('gives a merged donor\'s donation to the surviving record', function () {
    donateGateway();
    $survivor = Donor::factory()->create();
    $duplicate = Donor::factory()->create(['phone' => '03001234567']);
    $duplicate->forceFill(['merged_into_id' => $survivor->id, 'merged_at' => now()])->save();
    $duplicate->delete();

    $this->post('/donate', donateForm())->assertRedirect();

    expect(Donation::sole()->donor_id)->toBe($survivor->id);
});

it('refuses an archived donor politely', function () {
    donateGateway();
    Donor::factory()->create(['phone' => '03001234567'])->delete();

    $this->from('/donate')->post('/donate', donateForm())->assertSessionHasErrors('form');

    expect(Donation::query()->count())->toBe(0);
});

it('saves a new donor\'s CNIC for tax certificates', function () {
    donateGateway();

    $this->post('/donate', donateForm(['cnic' => '3520212345671']))->assertRedirect();

    $donor = Donor::sole();
    expect($donor->cnic)->toBe('35202-1234567-1')
        ->and($donor->cnic_hash)->toBe(Cnic::hash('3520212345671'));
});

it('does not copy a CNIC that belongs to another donor', function () {
    donateGateway();
    Donor::factory()->create(['cnic' => '35202-1234567-1']);

    $this->post('/donate', donateForm(['cnic' => '35202-1234567-1']))->assertRedirect();

    $donation = Donation::sole();
    expect($donation->donor->cnic)->toBeNull()
        ->and($donation->staff_note)->toContain('belongs to another donor record');
});

it('keeps the donor anonymous on request', function () {
    donateGateway();

    $this->post('/donate', donateForm(['is_anonymous' => '1', 'dedication' => 'My late father']))->assertRedirect();

    expect(Donation::sole())->is_anonymous->toBeTrue()->dedication->toBe('My late father');
});

// ── validation ──────────────────────────────────────────────────────────

it('rejects bad input', function (Closure $form, string $field) {
    donateGateway();

    $this->from('/donate')->post('/donate', $form())->assertSessionHasErrors($field);

    expect(Donation::query()->count())->toBe(0);
})->with([
    'amount below the minimum' => [fn () => donateForm(['amount' => '50']), 'amount'],
    'amount with letters' => [fn () => donateForm(['amount' => '5k']), 'amount'],
    'amount too big' => [fn () => donateForm(['amount' => '9999999999']), 'amount'],
    'not a Pakistani mobile' => [fn () => donateForm(['phone' => '12345']), 'phone'],
    'short CNIC' => [fn () => donateForm(['cnic' => '35202-12345']), 'cnic'],
    'method switched off' => [fn () => donateForm(['payment_method' => PaymentMethod::Easypaisa->value]), 'payment_method'],
    'cash is not for the website' => [fn () => donateForm(['payment_method' => PaymentMethod::Cash->value]), 'payment_method'],
    'no cause' => [fn () => donateForm(['fund_id' => null]), 'fund_id'],
    'inactive fund' => [fn () => donateForm(['fund_id' => Fund::factory()->inactive()->create()->id]), 'fund_id'],
    'closed campaign' => [fn () => donateForm(['fund_id' => null, 'campaign_id' => Campaign::factory()->closed()->create()->id]), 'campaign_id'],
    'no form key' => [fn () => donateForm(['idempotency_key' => 'abc']), 'idempotency_key'],
]);
