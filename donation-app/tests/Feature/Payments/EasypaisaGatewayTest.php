<?php

declare(strict_types=1);

use App\Enums\CheckoutType;
use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\GatewayOutcome;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\FundLedgerEntry;
use App\Models\Payment;
use App\Payments\DTOs\CallbackPayload;
use App\Payments\Exceptions\GatewayOperationNotSupported;
use App\Payments\Exceptions\InvalidGatewaySignature;
use App\Payments\GatewayRegistry;
use App\Payments\Gateways\Easypaisa\EasypaisaGateway;
use App\Payments\Gateways\Easypaisa\EasypaisaHash;
use App\Services\DonationService;
use App\Services\PaymentService;
use App\Settings\GatewaySettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// FR-PAY-01/02 — Easypaisa hosted checkout, direct wallet, status inquiry

const EP_KEY = '0123456789ABCDEF'; // 16 characters, like a real hash key

beforeEach(function () {
    $settings = app(GatewaySettings::class);
    $settings->easypaisa_enabled = true;
    $settings->easypaisa_mode = 'sandbox';
    $settings->easypaisa_store_id = '12345';
    $settings->easypaisa_hash_key = EP_KEY;
    $settings->easypaisa_username = 'ep-user';
    $settings->easypaisa_password = 'ep-pass';
    $settings->easypaisa_account_number = '654321';
    $settings->save();

    Http::preventStrayRequests();
});

function easypaisa(): EasypaisaGateway
{
    $gateway = app(GatewayRegistry::class)->get(PaymentMethod::Easypaisa);
    assert($gateway instanceof EasypaisaGateway);

    return $gateway;
}

function epDonation(string $amount = '2500.00'): Donation
{
    return app(DonationService::class)->create([
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => $amount,
        'channel' => DonationChannel::Web,
        'payment_method' => PaymentMethod::Easypaisa,
    ]);
}

/** What Easypaisa's status inquiry will answer for every order. */
function fakeInquiry(string $status, ?string $amount = '2500.00', string $code = '0000'): void
{
    Http::fake([
        '*/inquire-transaction' => fn (Request $r) => Http::response(array_filter([
            'responseCode' => $code,
            'responseDesc' => $code === '0000' ? 'SUCCESS' : 'Order not found',
            'orderId' => (string) $r['orderId'],
            'transactionStatus' => $status,
            'transactionAmount' => $amount,
            'transactionId' => 'EP-INQ-7788',
        ], fn ($v) => $v !== null && $v !== '')),
    ]);
}

// ── hash ────────────────────────────────────────────────────────────────

it('signs the hosted request exactly as Easypaisa specifies', function () {
    $fields = ['storeId' => '12345', 'amount' => '10.0', 'orderRefNum' => 'T1', 'emailAddr' => '', 'merchantHashedReq' => 'old'];

    // Sorted by name, empty values and the hash itself left out.
    $expected = base64_encode((string) openssl_encrypt('amount=10.0&orderRefNum=T1&storeId=12345', 'AES-128-ECB', EP_KEY, OPENSSL_RAW_DATA));

    expect(EasypaisaHash::make($fields, EP_KEY))->toBe($expected)
        ->and(openssl_decrypt(base64_decode(EasypaisaHash::make($fields, EP_KEY)), 'AES-128-ECB', EP_KEY, OPENSSL_RAW_DATA))
        ->toBe('amount=10.0&orderRefNum=T1&storeId=12345');
});

// ── settings ────────────────────────────────────────────────────────────

it('is offered only with store ID, a 16-character hash key, API login and account number', function (string $field, ?string $value) {
    expect(easypaisa()->isConfigured())->toBeTrue();

    $settings = app(GatewaySettings::class);
    $settings->{$field} = $value;
    $settings->save();

    expect(easypaisa()->isConfigured())->toBeFalse();
})->with([
    'no store id' => ['easypaisa_store_id', null],
    'short hash key' => ['easypaisa_hash_key', 'too-short'],
    'no api username' => ['easypaisa_username', null],
    'no api password' => ['easypaisa_password', null],
    'no account number' => ['easypaisa_account_number', null],
]);

// ── hosted checkout (website) ───────────────────────────────────────────

it('sends website donors to the Easypaisa hosted page with a signed form', function () {
    [$payment, $checkout] = app(PaymentService::class)->initiate(epDonation('2500.00'), 'https://example.test/payments/easypaisa/return');

    expect($checkout->type)->toBe(CheckoutType::FormPost)
        ->and($checkout->url)->toBe('https://easypaystg.easypaisa.com.pk/easypay/Index.jsf')
        ->and($checkout->fields['storeId'])->toBe('12345')
        ->and($checkout->fields['amount'])->toBe('2500.0')
        ->and($checkout->fields['orderRefNum'])->toBe($payment->reference)
        ->and($checkout->fields['postBackURL'])->toBe('https://example.test/payments/easypaisa/return')
        ->and($checkout->fields['merchantHashedReq'])->toBe(EasypaisaHash::make($checkout->fields, EP_KEY))
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('sends the exact amount, paisa included', function (string $amount, string $sent) {
    [, $checkout] = app(PaymentService::class)->initiate(epDonation($amount), 'https://example.test/return');

    expect($checkout->fields['amount'])->toBe($sent);
})->with([
    'whole rupees' => ['1000.00', '1000.0'],
    'fifty paisa' => ['1000.50', '1000.5'],
    'odd paisa' => ['99.99', '99.99'],
]);

it('uses the live Easypaisa site in live mode', function () {
    $settings = app(GatewaySettings::class);
    $settings->easypaisa_mode = 'live';
    $settings->save();

    [, $checkout] = app(PaymentService::class)->initiate(epDonation(), 'https://example.test/return');

    expect($checkout->url)->toBe('https://easypay.easypaisa.com.pk/easypay/Index.jsf');
});

it('builds the second hosted step with the auth token', function () {
    $step = easypaisa()->confirmStep('AUTH-TOKEN-123', 'https://example.test/payments/easypaisa/done');

    expect($step->type)->toBe(CheckoutType::FormPost)
        ->and($step->url)->toBe('https://easypaystg.easypaisa.com.pk/easypay/Confirm.jsf')
        ->and($step->fields)->toBe(['auth_token' => 'AUTH-TOKEN-123', 'postBackURL' => 'https://example.test/payments/easypaisa/done']);
});

// ── final redirect: never trusted, always re-checked ────────────────────

it('confirms a hosted payment only after the status inquiry says PAID', function () {
    fakeInquiry('PAID', '2500.00');
    $donation = epDonation('2500.00');
    [$payment] = app(PaymentService::class)->initiate($donation, 'https://example.test/return');

    $redirect = ['status' => '0000', 'desc' => 'Success', 'orderRefNumber' => $payment->reference];
    $payload = new CallbackPayload(PaymentMethod::Easypaisa, $redirect, http_build_query($redirect), httpMethod: 'GET');

    $result = easypaisa()->verifyCallback($payload);
    app(PaymentService::class)->applyResult($payment, $result);
    app(PaymentService::class)->applyResult($payment, easypaisa()->verifyCallback($payload)); // replay

    expect($result->outcome)->toBe(GatewayOutcome::Succeeded)
        ->and($payment->fresh()->gateway_ref)->toBe('EP-INQ-7788')
        ->and($donation->fresh()->status)->toBe(DonationStatus::Paid)
        ->and(FundLedgerEntry::query()->count())->toBe(1);

    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/easypay-service/rest/v4/inquire-transaction')
        && $r['orderId'] === $payment->reference
        && $r['storeId'] === '12345'
        && $r['accountNum'] === '654321'
        && $r->header('Credentials')[0] === base64_encode('ep-user:ep-pass'));
});

it('ignores a forged "success" redirect when Easypaisa says the payment failed', function () {
    fakeInquiry('FAILED', null);
    $donation = epDonation();
    [$payment] = app(PaymentService::class)->initiate($donation, 'https://example.test/return');

    $forged = ['status' => '0000', 'desc' => 'Success', 'orderRefNumber' => $payment->reference];
    app(PaymentService::class)->applyResult($payment, easypaisa()->verifyCallback(new CallbackPayload(PaymentMethod::Easypaisa, $forged, '')));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($donation->fresh()->status)->toBe(DonationStatus::Failed)
        ->and(FundLedgerEntry::query()->count())->toBe(0);
});

it('holds a payment for review when Easypaisa reports a different amount', function () {
    fakeInquiry('PAID', '25.00');
    [$payment] = app(PaymentService::class)->initiate(epDonation('2500.00'), 'https://example.test/return');

    app(PaymentService::class)->applyResult($payment, easypaisa()->queryStatus($payment));

    expect($payment->fresh()->status)->toBe(PaymentStatus::NeedsReview);
});

it('rejects a redirect with no usable order reference', function (array $fields) {
    Http::fake();

    expect(fn () => easypaisa()->verifyCallback(new CallbackPayload(PaymentMethod::Easypaisa, $fields, '')))
        ->toThrow(InvalidGatewaySignature::class);

    Http::assertNothingSent();
})->with([
    'missing' => [['status' => '0000']],
    'not a reference' => [['status' => '0000', 'orderRefNumber' => "T1'; DROP TABLE payments;--"]],
]);

// ── direct wallet (mobile app) ──────────────────────────────────────────

it('charges the Easypaisa wallet directly and confirms when the donor approves', function () {
    Http::fake(['*/initiate-ma-transaction' => fn (Request $r) => Http::response([
        'responseCode' => '0000',
        'responseDesc' => 'SUCCESS',
        'orderId' => (string) $r['orderId'],
        'storeId' => '12345',
        'transactionId' => 'EP-998877',
    ])]);
    $donation = epDonation('2500.00');

    [$payment, $checkout] = app(PaymentService::class)->initiate($donation, 'https://example.test/return', '03451234567', '123456');

    expect($checkout->type)->toBe(CheckoutType::AwaitingDonor)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->fresh()->gateway_ref)->toBe('EP-998877')
        ->and($donation->fresh()->status)->toBe(DonationStatus::Paid);

    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/initiate-ma-transaction')
        && $r['transactionAmount'] === '2500.00'
        && $r['transactionType'] === 'MA'
        && $r['mobileAccountNo'] === '03451234567'
        && ! isset($r['cnic']) // Easypaisa does not take the CNIC digits
        && $r->header('Credentials')[0] === base64_encode('ep-user:ep-pass'));
});

it('fails the payment when the Easypaisa wallet declines', function () {
    Http::fake(['*/initiate-ma-transaction' => Http::response(['responseCode' => '0001', 'responseDesc' => 'Transaction declined'])]);
    $donation = epDonation();

    [$payment] = app(PaymentService::class)->initiate($donation, 'https://example.test/return', '03451234567', '123456');

    expect($payment->fresh())
        ->status->toBe(PaymentStatus::Failed)
        ->response_message->toBe('Transaction declined')
        ->and($donation->fresh()->status)->toBe(DonationStatus::Failed);
});

// ── status inquiry ──────────────────────────────────────────────────────

it('maps Easypaisa statuses', function (string $status, string $code, GatewayOutcome $expected) {
    fakeInquiry($status, '2500.00', $code);
    [$payment] = app(PaymentService::class)->initiate(epDonation(), 'https://example.test/return');

    expect(easypaisa()->queryStatus($payment)->outcome)->toBe($expected);
})->with([
    'paid' => ['PAID', '0000', GatewayOutcome::Succeeded],
    'pending' => ['PENDING', '0000', GatewayOutcome::Pending],
    'failed' => ['FAILED', '0000', GatewayOutcome::Failed],
    'dropped' => ['DROPPED', '0000', GatewayOutcome::Failed],
    'inquiry failed — decide later' => ['', '0001', GatewayOutcome::Pending],
]);

it('explains that Easypaisa refunds are manual', function () {
    easypaisa()->refund(Payment::factory()->create(), '100.00');
})->throws(GatewayOperationNotSupported::class, 'manually');
