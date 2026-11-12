<?php

declare(strict_types=1);

use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\WebhookOutcome;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\FundLedgerEntry;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Payments\DTOs\CallbackPayload;
use App\Payments\GatewayRegistry;
use App\Payments\Gateways\JazzCash\JazzCashHash;
use App\Services\DonationService;
use App\Services\PaymentCallbackService;
use App\Services\PaymentService;
use App\Settings\GatewaySettings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeGateway;

// FR-PAY-02, CLAUDE.md rule 8 — gateway callbacks and returns: store, verify, apply once

const CB_JC_SALT = 'cb-jazzcash-salt';

function callbacks(): PaymentCallbackService
{
    return app(PaymentCallbackService::class);
}

function cbDonation(PaymentMethod $method = PaymentMethod::JazzCash, string $amount = '3000.00'): Donation
{
    return app(DonationService::class)->create([
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => $amount,
        'channel' => DonationChannel::Web,
        'payment_method' => $method,
    ]);
}

function cbFakePayment(): Payment
{
    app(GatewayRegistry::class)->register(new FakeGateway);
    [$payment] = app(PaymentService::class)->initiate(cbDonation(), 'https://example.test/return');

    return $payment;
}

function cbPayload(array $fields): CallbackPayload
{
    return new CallbackPayload(PaymentMethod::JazzCash, $fields, http_build_query($fields), ['content-type' => ['application/x-www-form-urlencoded']], 'POST', 'https://example.test/payments/jazzcash/return', '203.0.113.7');
}

function useRealJazzCash(): void
{
    $s = app(GatewaySettings::class);
    $s->jazzcash_enabled = true;
    $s->jazzcash_mode = 'sandbox';
    $s->jazzcash_merchant_id = 'MC10001';
    $s->jazzcash_password = 'jc-password';
    $s->jazzcash_integrity_salt = CB_JC_SALT;
    $s->save();
}

function useRealEasypaisa(): void
{
    $s = app(GatewaySettings::class);
    $s->easypaisa_enabled = true;
    $s->easypaisa_mode = 'sandbox';
    $s->easypaisa_store_id = '12345';
    $s->easypaisa_hash_key = '0123456789ABCDEF';
    $s->easypaisa_username = 'ep-user';
    $s->easypaisa_password = 'ep-pass';
    $s->easypaisa_account_number = '654321';
    $s->save();
}

// ── the service ─────────────────────────────────────────────────────────

it('stores, verifies and applies a callback, then ignores the replay', function () {
    $payment = cbFakePayment();
    $payload = cbPayload(FakeGateway::callbackFields($payment, gatewayRef: 'FG-77'));

    $first = callbacks()->handle($payload);
    $replay = callbacks()->handle($payload);

    expect($first->fresh())
        ->outcome->toBe(WebhookOutcome::Processed)
        ->signature_valid->toBeTrue()
        ->payment_id->toBe($payment->id)
        ->reference->toBe($payment->reference)
        ->gateway_ref->toBe('FG-77')
        ->ip->toBe('203.0.113.7')
        ->and($replay->fresh()->outcome)->toBe(WebhookOutcome::Duplicate)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and(FundLedgerEntry::query()->count())->toBe(1)
        ->and(PaymentWebhookLog::query()->count())->toBe(2); // both stored, word for word
});

it('rejects a callback with a bad signature and changes nothing', function () {
    $payment = cbFakePayment();
    $fields = FakeGateway::callbackFields($payment);
    $fields['amount'] = '1.00';

    $log = callbacks()->handle(cbPayload($fields))->fresh();

    expect($log->outcome)->toBe(WebhookOutcome::Rejected)
        ->and($log->signature_valid)->toBeFalse()
        ->and($log->payload)->toContain('amount=1.00') // still kept as evidence
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('rejects a correctly signed callback for a payment we never started', function () {
    app(GatewayRegistry::class)->register(new FakeGateway);
    $stranger = (new Payment)->forceFill(['reference' => 'T000000000000ZZZZZ', 'amount' => '10.00']);

    $log = callbacks()->handle(cbPayload(FakeGateway::callbackFields($stranger)))->fresh();

    expect($log->outcome)->toBe(WebhookOutcome::Rejected)
        ->and($log->error)->toContain('No JazzCash payment with reference T000000000000ZZZZZ');
});

it('records an error but keeps the callback when processing breaks', function () {
    $payment = cbFakePayment();
    $donation = Donation::query()->findOrFail($payment->donation_id);
    // Broken data: a ledger line already exists for this donation, so confirming it fails.
    FundLedgerEntry::query()->create([
        'fund_id' => $donation->fund_id, 'entry_type' => 'credit', 'amount' => '1.00', 'balance_after' => '0.00',
        'source_type' => $donation->getMorphClass(), 'source_id' => $donation->id, 'description' => 'Stray', 'posted_at' => now(),
    ]);

    $log = callbacks()->handle(cbPayload(FakeGateway::callbackFields($payment)))->fresh();

    expect($log->outcome)->toBe(WebhookOutcome::Error)
        ->and($log->error)->not->toBeEmpty()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

// ── JazzCash over HTTP ──────────────────────────────────────────────────

it('takes the JazzCash return post without a CSRF token and shows the donor the result', function () {
    useRealJazzCash();
    $donation = cbDonation(amount: '3000.00');
    [$payment] = app(PaymentService::class)->initiate($donation, route('payments.jazzcash.return'));

    $fields = ['pp_ResponseCode' => '000', 'pp_ResponseMessage' => 'Thank you', 'pp_TxnRefNo' => $payment->reference, 'pp_Amount' => '300000', 'pp_RetreivalReferenceNo' => '261116000999'];
    $fields['pp_SecureHash'] = JazzCashHash::make($fields, CB_JC_SALT);

    $this->post('/payments/jazzcash/return', $fields)
        ->assertRedirect(route('payments.result', ['reference' => $payment->reference]));

    expect($donation->fresh()->status)->toBe(DonationStatus::Paid)
        ->and(PaymentWebhookLog::query()->sole()->outcome)->toBe(WebhookOutcome::Processed);

    $this->get(route('payments.result', ['reference' => $payment->reference]))
        ->assertOk()
        ->assertSee('Thank you!')
        ->assertSee($donation->donation_no)
        ->assertSee('3,000.00')
        ->assertDontSee($donation->donor->name); // no personal details on this page
});

it('answers the JazzCash server notification with OK', function () {
    useRealJazzCash();
    [$payment] = app(PaymentService::class)->initiate(cbDonation(), route('payments.jazzcash.return'));

    $fields = ['pp_ResponseCode' => '000', 'pp_TxnRefNo' => $payment->reference, 'pp_Amount' => '300000', 'pp_RetreivalReferenceNo' => '261116000111'];
    $fields['pp_SecureHash'] = JazzCashHash::make($fields, CB_JC_SALT);

    $this->postJson('/payments/jazzcash/notify', $fields)->assertOk()->assertSeeText('OK');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Succeeded);
});

it('tells the donor when a forged JazzCash return is not accepted', function () {
    useRealJazzCash();
    [$payment] = app(PaymentService::class)->initiate(cbDonation(), route('payments.jazzcash.return'));

    $this->post('/payments/jazzcash/return', ['pp_ResponseCode' => '000', 'pp_TxnRefNo' => $payment->reference, 'pp_Amount' => '300000', 'pp_SecureHash' => 'FORGED'])
        ->assertRedirect(route('payments.result', ['reference' => 'unknown']));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
    $this->get(route('payments.result', ['reference' => 'unknown']))->assertSee('We could not find this payment');
});

// ── Easypaisa over HTTP ─────────────────────────────────────────────────

it('forwards the Easypaisa auth token to the confirm step', function () {
    useRealEasypaisa();

    $this->get('/payments/easypaisa/return?auth_token=AUTH-123')
        ->assertOk()
        ->assertSee('https://easypaystg.easypaisa.com.pk/easypay/Confirm.jsf', false)
        ->assertSee('value="AUTH-123"', false)
        ->assertSee(route('payments.easypaisa.done'), false);
});

it('finishes an Easypaisa payment only after the status inquiry confirms it', function () {
    useRealEasypaisa();
    Http::fake(['*/inquire-transaction' => fn (HttpRequest $r) => Http::response([
        'responseCode' => '0000', 'orderId' => (string) $r['orderId'], 'transactionStatus' => 'PAID',
        'transactionAmount' => '3000.00', 'transactionId' => 'EP-555',
    ])]);
    $donation = cbDonation(PaymentMethod::Easypaisa, '3000.00');
    [$payment] = app(PaymentService::class)->initiate($donation, route('payments.easypaisa.return'));

    $this->get('/payments/easypaisa/done?status=0000&desc=Success&orderRefNumber='.$payment->reference)
        ->assertRedirect(route('payments.result', ['reference' => $payment->reference]));

    expect($donation->fresh()->status)->toBe(DonationStatus::Paid);
});

it('shows "we are confirming" while a payment is still pending', function () {
    $payment = cbFakePayment();

    $this->get(route('payments.result', ['reference' => $payment->reference]))->assertSee('We are confirming your payment');
});

it('limits how often the return pages can be hit', function () {
    useRealJazzCash();

    foreach (range(1, 60) as $i) {
        $this->post('/payments/jazzcash/return', ['pp_TxnRefNo' => 'T1']);
    }

    $this->post('/payments/jazzcash/return', ['pp_TxnRefNo' => 'T1'])->assertStatus(429);
});
