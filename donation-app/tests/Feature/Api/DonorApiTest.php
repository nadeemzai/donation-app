<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Api\V1\DonationController;
use App\Models\Campaign;
use App\Models\DeviceToken;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\Payment;
use App\Models\User;
use App\Payments\GatewayRegistry;
use App\Services\DonationService;
use App\Services\TaxCertificateService;
use App\Settings\GatewaySettings;
use App\Support\FiscalYear;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeGateway;

// Week 7 Day 4 — donating from the app, status polling, proof upload, /me, receipts, devices

beforeEach(function () {
    Storage::fake('local');

    $bank = app(GatewaySettings::class);
    $bank->bank_enabled = true;
    $bank->bank_name = 'Meezan Bank';
    $bank->bank_account_title = 'Imdaad Welfare Trust';
    $bank->bank_iban = 'PK36MEZN0000001123456702';
    $bank->save();
});

function appGateway(): FakeGateway
{
    $gateway = new FakeGateway(PaymentMethod::JazzCash);
    app(GatewayRegistry::class)->register($gateway);

    return $gateway;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function appDonation(array $overrides = []): array
{
    return array_merge([
        'fund_code' => $overrides['fund_code'] ?? Fund::factory()->create()->code,
        'amount' => '5,000',
        'payment_method' => PaymentMethod::JazzCash->value,
        'name' => 'Ayesha Khan',
        'phone' => '0300-1234567',
        'email' => 'ayesha@example.com',
        'idempotency_key' => (string) Str::uuid(),
    ], $overrides);
}

function appPaid(Donor $donor, string $amount = '5000.00'): Donation
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

// ── starting a donation ─────────────────────────────────────────────────

it('starts a guest wallet donation and sends the app to the gateway', function () {
    $gateway = appGateway();

    $response = $this->postJson('/api/v1/donations', appDonation())
        ->assertCreated()
        ->assertJsonPath('data.donation.status.value', 'pending')
        ->assertJsonPath('data.donation.amount.amount', '5000.00')
        ->assertJsonPath('data.donation.channel.value', 'mobile')
        ->assertJsonPath('data.next_step', 'open_checkout')
        ->assertJsonPath('data.checkout.type', 'redirect')
        ->assertJsonPath('data.payment.status.value', 'pending')
        ->assertJsonPath('data.proof_url', null);

    expect(Donation::sole()->donor)->name->toBe('Ayesha Khan')->phone->toBe('03001234567')
        ->and($response->json('data.status_url'))->toContain('signature=')
        ->and($gateway->initiated)->toHaveCount(1)
        ->and($gateway->initiated[0]['request']->directWallet)->toBeFalse();
});

it('charges a wallet directly when the app sends the number and CNIC digits', function () {
    $gateway = appGateway();

    $this->postJson('/api/v1/donations', appDonation(['wallet_number' => '0333 7654321', 'cnic_last6' => '345671']))->assertCreated();

    expect($gateway->initiated[0]['request'])
        ->directWallet->toBeTrue()
        ->mobileNumber->toBe('03337654321')
        ->cnicLast6->toBe('345671');
});

it('uses the logged-in donor and needs no name or phone', function () {
    appGateway();
    $donor = Donor::factory()->create();
    Sanctum::actingAs($donor);

    $this->postJson('/api/v1/donations', appDonation(['name' => null, 'phone' => null, 'email' => null]))->assertCreated();

    expect(Donation::sole()->donor_id)->toBe($donor->id)
        ->and(Donor::query()->count())->toBe(1);
});

it('gives a campaign donation to the campaign', function () {
    appGateway();
    $campaign = Campaign::factory()->live()->create();

    $this->postJson('/api/v1/donations', appDonation(['fund_code' => null, 'campaign_slug' => $campaign->slug]))->assertCreated();

    expect(Donation::sole())->campaign_id->toBe($campaign->id)->fund_id->toBe($campaign->fund_id);
});

it('never charges twice when the app retries with the same key', function () {
    $gateway = appGateway();
    $body = appDonation();

    $this->postJson('/api/v1/donations', $body)->assertCreated();
    $this->postJson('/api/v1/donations', $body, ['Idempotency-Key' => $body['idempotency_key']])
        ->assertOk()
        ->assertJsonPath('data.next_step', 'wait');

    expect(Donation::query()->count())->toBe(1)
        ->and(Payment::query()->count())->toBe(1)
        ->and($gateway->initiated)->toHaveCount(1);
});

it('cancels the donation and says no money was taken when the gateway is down', function () {
    $gateway = appGateway();
    $gateway->failToInitiate = true;

    $this->postJson('/api/v1/donations', appDonation())
        ->assertStatus(502)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'We could not reach JazzCash just now. No money was taken. Please try again, or choose another way to pay.');

    expect(Donation::sole()->status)->toBe(DonationStatus::Cancelled);
});

it('rejects a bad donation request', function (Closure $body, string $field) {
    appGateway();

    $this->postJson('/api/v1/donations', $body())->assertStatus(422)->assertJsonValidationErrors([$field], 'errors');

    expect(Donation::query()->count())->toBe(0);
})->with([
    'no idempotency key' => [fn () => appDonation(['idempotency_key' => null]), 'idempotency_key'],
    'too small' => [fn () => appDonation(['amount' => '50']), 'amount'],
    'method switched off' => [fn () => appDonation(['payment_method' => 'easypaisa']), 'payment_method'],
    'wallet with bank transfer' => [fn () => appDonation(['payment_method' => 'bank_transfer', 'wallet_number' => '03001234567', 'cnic_last6' => '123456']), 'wallet_number'],
    'wallet without cnic digits' => [fn () => appDonation(['wallet_number' => '03001234567']), 'cnic_last6'],
    'guest without phone' => [fn () => appDonation(['phone' => null]), 'phone'],
    'unknown fund' => [fn () => appDonation(['fund_code' => 'NOPE']), 'fund_code'],
    'closed campaign' => [fn () => appDonation(['fund_code' => null, 'campaign_slug' => (string) Campaign::factory()->closed()->create()->slug]), 'campaign_slug'],
]);

// ── status polling ──────────────────────────────────────────────────────

it('reports the status, and asks the gateway itself when the answer is late', function () {
    $gateway = appGateway();
    $statusUrl = (string) $this->postJson('/api/v1/donations', appDonation())->json('data.status_url');

    $this->getJson($statusUrl)
        ->assertOk()
        ->assertJsonPath('data.is_final', false)
        ->assertJsonPath('data.poll_after_seconds', 5)
        ->assertJsonPath('data.next_step', 'wait');

    $gateway->nextStatus = FakeGateway::success(Payment::sole());
    $this->travel(31)->seconds();

    $this->getJson($statusUrl)
        ->assertOk()
        ->assertJsonPath('data.is_final', true)
        ->assertJsonPath('data.next_step', 'done')
        ->assertJsonPath('data.donation.status.value', 'paid')
        ->assertJsonPath('data.payment.status.value', 'succeeded')
        ->assertJsonPath('data.donation.receipt.download_url', fn (string $url): bool => str_contains($url, '/api/v1/me/receipts/'));
});

it('refuses a status link without a valid signature', function () {
    appGateway();
    $this->postJson('/api/v1/donations', appDonation());

    $this->getJson('/api/v1/donations/'.Donation::sole()->donation_no.'/status')->assertForbidden()->assertJsonPath('success', false);
});

// ── bank transfer & proof ───────────────────────────────────────────────

it('takes a bank transfer, shows the account, and accepts one proof', function () {
    $response = $this->postJson('/api/v1/donations', appDonation(['payment_method' => 'bank_transfer']))
        ->assertCreated()
        ->assertJsonPath('data.next_step', 'upload_proof')
        ->assertJsonPath('data.bank.iban', 'PK36MEZN0000001123456702')
        ->assertJsonPath('data.checkout', null);

    $proofUrl = (string) $response->json('data.proof_url');

    $this->post($proofUrl, ['reference' => 'RAAST-88120044', 'proof' => UploadedFile::fake()->image('transfer.jpg')], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.next_step', 'wait');

    $donation = Donation::sole();
    expect($donation->reference)->toBe('RAAST-88120044')
        ->and($donation->getFirstMedia(Donation::PROOF)?->disk)->toBe('local');

    $this->post($proofUrl, ['reference' => 'X', 'proof' => UploadedFile::fake()->image('again.jpg')], ['Accept' => 'application/json'])
        ->assertStatus(409);

    $this->getJson((string) $response->json('data.status_url'))->assertJsonPath('data.next_step', 'wait')->assertJsonPath('data.is_final', false);
});

it('does not take proof for a wallet donation', function () {
    appGateway();
    $this->postJson('/api/v1/donations', appDonation());
    $url = DonationController::proofUrl(Donation::sole());

    $this->post($url, ['reference' => 'X', 'proof' => UploadedFile::fake()->image('t.jpg')], ['Accept' => 'application/json'])
        ->assertStatus(422);
});

it('needs a reference and a file for the proof', function () {
    $proofUrl = (string) $this->postJson('/api/v1/donations', appDonation(['payment_method' => 'bank_transfer']))->json('data.proof_url');

    $this->postJson($proofUrl, [])->assertStatus(422)->assertJsonValidationErrors(['reference', 'proof'], 'errors');
});

// ── me ──────────────────────────────────────────────────────────────────

it('needs a token for /me', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('shows the donor with totals', function () {
    $donor = Donor::factory()->create(['cnic' => '35202-1234567-1']);
    appPaid($donor, '5000.00');
    appPaid($donor, '2500.00');
    Sanctum::actingAs($donor);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.donor_code', $donor->donor_code)
        ->assertJsonPath('data.cnic_masked', '35202-•••••••-1')
        ->assertJsonPath('data.totals.given.amount', '7500.00')
        ->assertJsonPath('data.totals.donations', 2);
});

it('updates preferences and adds a CNIC once, but never name or contact details', function () {
    $donor = Donor::factory()->create(['cnic' => null]);
    Sanctum::actingAs($donor);

    $this->putJson('/api/v1/me', ['language' => 'ur', 'whatsapp_opt_in' => false, 'city' => 'Multan', 'cnic' => '35202 1234567 1'])
        ->assertOk()
        ->assertJsonPath('data.preferences.language', 'ur')
        ->assertJsonPath('data.preferences.whatsapp_opt_in', false)
        ->assertJsonPath('data.city', 'Multan')
        ->assertJsonPath('data.has_cnic', true);

    $this->putJson('/api/v1/me', ['cnic' => '42101-7654321-9'])->assertStatus(422)->assertJsonValidationErrors(['cnic'], 'errors');
    $this->putJson('/api/v1/me', ['name' => 'Someone Else', 'email' => 'x@example.com'])->assertStatus(422)->assertJsonValidationErrors(['name', 'email'], 'errors');

    expect($donor->fresh()?->cnic)->toBe('35202-1234567-1');
});

it('lists only the donor\'s own donations, newest first', function () {
    $donor = Donor::factory()->create();
    appPaid($donor, '1000.00');
    $newest = appPaid($donor, '2000.00');
    $other = appPaid(Donor::factory()->create());
    Sanctum::actingAs($donor);

    $response = $this->getJson('/api/v1/me/donations')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.number', $newest->donation_no)
        ->assertJsonPath('meta.pagination.total', 2);

    expect(collect($response->json('data'))->pluck('number'))->not->toContain($other->donation_no);
    $this->getJson('/api/v1/me/donations?status=pending')->assertOk()->assertJsonCount(0, 'data');
});

it('downloads the donor\'s own receipt and nobody else\'s', function () {
    $donor = Donor::factory()->create();
    $mine = appPaid($donor)->receipt()->sole();
    $theirs = appPaid(Donor::factory()->create())->receipt()->sole();
    Sanctum::actingAs($donor);

    $this->get("/api/v1/me/receipts/{$mine->id}/download")->assertOk()->assertDownload($mine->receipt_no.'.pdf');
    $this->getJson("/api/v1/me/receipts/{$theirs->id}/download")->assertNotFound()->assertJsonPath('success', false);
});

it('lists and downloads the donor\'s tax certificates', function () {
    $donor = Donor::factory()->create(['cnic' => '35202-1234567-1']);
    appPaid($donor);
    $certificate = app(TaxCertificateService::class)->generate($donor, FiscalYear::containing(now()));
    Sanctum::actingAs($donor);

    $this->getJson('/api/v1/me/tax-certificates')
        ->assertOk()
        ->assertJsonPath('data.0.number', $certificate->certificate_no)
        ->assertJsonPath('data.0.total.amount', '5000.00');

    $this->get("/api/v1/me/tax-certificates/{$certificate->id}/download")->assertOk()->assertDownload($certificate->fileName());

    Sanctum::actingAs(Donor::factory()->create());
    $this->getJson("/api/v1/me/tax-certificates/{$certificate->id}/download")->assertNotFound();
});

// ── devices ─────────────────────────────────────────────────────────────

it('registers a push token, moves it to whoever logs in on that phone, and removes it', function () {
    $first = Donor::factory()->create();
    $second = Donor::factory()->create();

    Sanctum::actingAs($first);
    $this->postJson('/api/v1/devices', ['token' => 'fcm-abc', 'platform' => 'android', 'app_version' => '1.0.0'])->assertCreated();

    Sanctum::actingAs($second);
    $this->postJson('/api/v1/devices', ['token' => 'fcm-abc', 'platform' => 'android'])->assertOk();
    expect(DeviceToken::sole()->donor_id)->toBe($second->id);

    $this->deleteJson('/api/v1/devices', ['token' => 'fcm-abc'])->assertOk();
    expect(DeviceToken::query()->count())->toBe(0);
});

it('rejects an unknown platform', function () {
    Sanctum::actingAs(Donor::factory()->create());

    $this->postJson('/api/v1/devices', ['token' => 'x', 'platform' => 'windows'])->assertStatus(422)->assertJsonValidationErrors(['platform'], 'errors');
});
