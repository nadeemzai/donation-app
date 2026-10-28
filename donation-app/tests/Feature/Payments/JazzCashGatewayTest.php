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
use App\Payments\Gateways\JazzCash\JazzCashGateway;
use App\Payments\Gateways\JazzCash\JazzCashHash;
use App\Services\DonationService;
use App\Services\PaymentService;
use App\Settings\GatewaySettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// FR-PAY-01/02 — JazzCash hosted page, direct wallet (MWALLET), callback hash, status inquiry

const JC_SALT = 'jc-test-salt-123';

beforeEach(function () {
    $settings = app(GatewaySettings::class);
    $settings->jazzcash_enabled = true;
    $settings->jazzcash_mode = 'sandbox';
    $settings->jazzcash_merchant_id = 'MC10001';
    $settings->jazzcash_password = 'jc-password';
    $settings->jazzcash_integrity_salt = JC_SALT;
    $settings->save();

    Http::preventStrayRequests();
});

function jazzcash(): JazzCashGateway
{
    return app(GatewayRegistry::class)->get(PaymentMethod::JazzCash);
}

function jcDonation(string $amount = '2500.00'): Donation
{
    return app(DonationService::class)->create([
        'donor_id' => Donor::factory()->create()->id,
        'fund_id' => Fund::factory()->create()->id,
        'amount' => $amount,
        'channel' => DonationChannel::Web,
        'payment_method' => PaymentMethod::JazzCash,
    ]);
}

/**
 * @param  array<string, string>  $fields
 * @return array<string, string>
 */
function jcSigned(array $fields): array
{
    $fields['pp_SecureHash'] = JazzCashHash::make($fields, JC_SALT);

    return $fields;
}

/** Fake the wallet API: answer with $code for whatever reference/amount we sent. */
function fakeWallet(string $code, string $message = 'Thank you for using JazzCash'): void
{
    Http::fake([
        '*/DoMWalletTransaction' => fn (Request $r) => Http::response(jcSigned([
            'pp_ResponseCode' => $code,
            'pp_ResponseMessage' => $message,
            'pp_TxnRefNo' => (string) $r['pp_TxnRefNo'],
            'pp_Amount' => (string) $r['pp_Amount'],
            'pp_TxnCurrency' => 'PKR',
            'pp_RetreivalReferenceNo' => '261116000123',
        ])),
    ]);
}

// ── hash ────────────────────────────────────────────────────────────────

it('builds the secure hash exactly as JazzCash specifies', function () {
    $fields = [
        'pp_TxnRefNo' => 'T1',
        'pp_Amount' => '250000',
        'pp_MerchantID' => 'MC1',
        'pp_Language' => 'EN',
        'pp_Description' => 'Donation',
        'pp_SubMerchantID' => '',  // empty: skipped
        'ppmpf_1' => '',           // empty: skipped
        'pp_SecureHash' => 'OLD',  // never part of its own hash
        'unrelated' => 'x',        // not a pp_ field: skipped
    ];

    // Sorted by name: pp_Amount, pp_Description, pp_Language, pp_MerchantID, pp_TxnRefNo
    $expected = strtoupper(hash_hmac('sha256', 'salt&250000&Donation&EN&MC1&T1', 'salt'));

    expect(JazzCashHash::make($fields, 'salt'))->toBe($expected)
        ->and(JazzCashHash::verify(['pp_SecureHash' => strtolower($expected)] + $fields, 'salt'))->toBeTrue()
        ->and(JazzCashHash::verify(['pp_SecureHash' => $expected, 'pp_Amount' => '1'] + $fields, 'salt'))->toBeFalse()
        ->and(JazzCashHash::verify($fields, 'other-salt'))->toBeFalse();
});

// ── settings ────────────────────────────────────────────────────────────

it('is offered to donors only when switched on and fully configured', function () {
    expect(app(GatewayRegistry::class)->available())->toHaveCount(1);

    $settings = app(GatewaySettings::class);
    $settings->jazzcash_integrity_salt = null;
    $settings->save();

    expect(jazzcash()->isConfigured())->toBeFalse()
        ->and(app(GatewayRegistry::class)->available())->toBe([]);
});

// ── hosted page (website) ───────────────────────────────────────────────

it('sends website donors to the JazzCash hosted page with a signed form', function () {
    [$payment, $checkout] = app(PaymentService::class)->initiate(jcDonation('2500.00'), 'https://example.test/payments/jazzcash/return');

    expect($checkout->type)->toBe(CheckoutType::FormPost)
        ->and($checkout->url)->toBe('https://sandbox.jazzcash.com.pk/CustomerPortal/transactionmanagement/merchantform/')
        ->and($checkout->fields['pp_Amount'])->toBe('250000')
        ->and($checkout->fields['pp_TxnRefNo'])->toBe($payment->reference)
        ->and($checkout->fields['pp_TxnCurrency'])->toBe('PKR')
        ->and($checkout->fields['pp_ReturnURL'])->toBe('https://example.test/payments/jazzcash/return')
        ->and($checkout->fields['pp_BillReference'])->toMatch('/^DN\d+$/')
        ->and($checkout->fields['pp_TxnDateTime'])->toMatch('/^\d{14}$/')
        ->and(JazzCashHash::verify($checkout->fields, JC_SALT))->toBeTrue()
        ->and($checkout->fields)->not->toHaveKey('pp_CNIC')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('uses the live JazzCash site in live mode', function () {
    $settings = app(GatewaySettings::class);
    $settings->jazzcash_mode = 'live';
    $settings->save();

    [, $checkout] = app(PaymentService::class)->initiate(jcDonation(), 'https://example.test/return');

    expect($checkout->url)->toStartWith('https://payments.jazzcash.com.pk/')
        ->and(Payment::query()->sole()->mode)->toBe('live');
});

// ── direct wallet (mobile app) ──────────────────────────────────────────

it('charges the wallet directly and confirms the donation when the donor approves', function () {
    fakeWallet('000');
    $donation = jcDonation('2500.00');

    [$payment, $checkout] = app(PaymentService::class)->initiate($donation, 'https://example.test/return', '03123456789', '345678');

    expect($checkout->type)->toBe(CheckoutType::AwaitingDonor)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->fresh()->gateway_ref)->toBe('261116000123')
        ->and($donation->fresh()->status)->toBe(DonationStatus::Paid)
        ->and($donation->fund->fresh()->balance)->toBe('2500.00');

    Http::assertSent(fn (Request $r): bool => $r->url() === 'https://sandbox.jazzcash.com.pk/ApplicationAPI/API/2.0/Purchase/DoMWalletTransaction'
        && $r['pp_MobileNumber'] === '03123456789'
        && $r['pp_CNIC'] === '345678'
        && $r['pp_Amount'] === '250000'
        && JazzCashHash::verify($r->data(), JC_SALT));
});

it('never stores the CNIC digits anywhere', function () {
    fakeWallet('000');

    app(PaymentService::class)->initiate(jcDonation(), 'https://example.test/return', '03123456789', '987321');

    foreach (['payments', 'activity_log', 'donations'] as $table) {
        expect(json_encode(DB::table($table)->get()))->not->toContain('987321');
    }
});

it('waits when the donor has not approved yet', function () {
    fakeWallet('157', 'Transaction is pending');
    $donation = jcDonation();

    [$payment, $checkout] = app(PaymentService::class)->initiate($donation, 'https://example.test/return', '03123456789', '345678');

    expect($checkout->type)->toBe(CheckoutType::AwaitingDonor)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($donation->fresh()->status)->toBe(DonationStatus::Pending);
});

it('fails the payment when the wallet declines', function () {
    fakeWallet('999', 'Insufficient balance');
    $donation = jcDonation();

    [$payment] = app(PaymentService::class)->initiate($donation, 'https://example.test/return', '03123456789', '345678');

    expect($payment->fresh())
        ->status->toBe(PaymentStatus::Failed)
        ->response_message->toBe('Insufficient balance')
        ->and($donation->fresh()->status)->toBe(DonationStatus::Failed);
});

it('refuses an answer from the wallet API with a wrong hash', function () {
    Http::fake(['*/DoMWalletTransaction' => Http::response(['pp_ResponseCode' => '000', 'pp_TxnRefNo' => 'X', 'pp_SecureHash' => 'FORGED'])]);
    $donation = jcDonation();

    expect(fn () => app(PaymentService::class)->initiate($donation, 'https://example.test/return', '03123456789', '345678'))
        ->toThrow(InvalidGatewaySignature::class);

    expect(Payment::query()->sole()->status)->toBe(PaymentStatus::Failed)
        ->and($donation->fresh()->status)->toBe(DonationStatus::Pending);
});

it('checks the wallet number and CNIC digits before calling JazzCash', function (string $wallet, string $cnic, string $message) {
    Http::fake();

    expect(fn () => app(PaymentService::class)->initiate(jcDonation(), 'https://example.test/return', $wallet, $cnic))
        ->toThrow(DomainException::class, $message);

    Http::assertNothingSent();
})->with([
    'short number' => ['0312345', '345678', '03XXXXXXXXX'],
    'not a mobile' => ['04212345678', '345678', '03XXXXXXXXX'],
    'cnic too short' => ['03123456789', '3456', 'last 6 digits'],
    'cnic letters' => ['03123456789', '34567a', 'last 6 digits'],
]);

// ── callback (hosted page return) ───────────────────────────────────────

it('accepts a correctly signed callback and confirms once, even if it arrives twice', function () {
    $donation = jcDonation('1500.00');
    [$payment] = app(PaymentService::class)->initiate($donation, 'https://example.test/return');

    $fields = jcSigned([
        'pp_ResponseCode' => '000',
        'pp_ResponseMessage' => 'Thank you for Using JazzCash',
        'pp_TxnRefNo' => $payment->reference,
        'pp_Amount' => '150000',
        'pp_TxnCurrency' => 'PKR',
        'pp_RetreivalReferenceNo' => '261116555000',
    ]);
    $payload = new CallbackPayload(PaymentMethod::JazzCash, $fields, http_build_query($fields));

    $result = jazzcash()->verifyCallback($payload);
    expect($result->outcome)->toBe(GatewayOutcome::Succeeded)
        ->and($result->amount)->toBe('1500.00')
        ->and($result->gatewayRef)->toBe('261116555000')
        ->and($result->raw)->not->toHaveKey('pp_SecureHash');

    app(PaymentService::class)->applyResult($payment, $result);
    app(PaymentService::class)->applyResult($payment, jazzcash()->verifyCallback($payload));

    expect($donation->fresh()->status)->toBe(DonationStatus::Paid)
        ->and(FundLedgerEntry::query()->count())->toBe(1);
});

it('rejects a callback whose amount was changed after signing', function () {
    $fields = jcSigned(['pp_ResponseCode' => '000', 'pp_TxnRefNo' => 'T261116101010ABCDE', 'pp_Amount' => '150000']);
    $fields['pp_Amount'] = '15000000';

    jazzcash()->verifyCallback(new CallbackPayload(PaymentMethod::JazzCash, $fields, http_build_query($fields)));
})->throws(InvalidGatewaySignature::class);

it('rejects a callback with no hash or signed with another salt', function (array $fields) {
    jazzcash()->verifyCallback(new CallbackPayload(PaymentMethod::JazzCash, $fields, http_build_query($fields)));
})->with([
    'no hash' => [['pp_ResponseCode' => '000', 'pp_TxnRefNo' => 'T1', 'pp_Amount' => '100']],
    'other salt' => [['pp_ResponseCode' => '000', 'pp_TxnRefNo' => 'T1', 'pp_Amount' => '100', 'pp_SecureHash' => JazzCashHash::make(['pp_ResponseCode' => '000', 'pp_TxnRefNo' => 'T1', 'pp_Amount' => '100'], 'wrong')]],
])->throws(InvalidGatewaySignature::class);

// ── status inquiry ──────────────────────────────────────────────────────

it('asks JazzCash for the status of a payment', function (string $inquiryCode, string $paymentCode, GatewayOutcome $expected) {
    [$payment] = app(PaymentService::class)->initiate(jcDonation('2500.00'), 'https://example.test/return');

    Http::fake(['*/PaymentInquiry/Inquire' => Http::response(jcSigned([
        'pp_ResponseCode' => $inquiryCode,
        'pp_ResponseMessage' => 'Inquiry done',
        'pp_PaymentResponseCode' => $paymentCode,
        'pp_TxnRefNo' => $payment->reference,
        'pp_Amount' => '250000',
        'pp_RetreivalReferenceNo' => '261116777000',
    ]))]);

    $result = jazzcash()->queryStatus($payment);

    expect($result->outcome)->toBe($expected)
        ->and($result->reference)->toBe($payment->reference);

    Http::assertSent(fn (Request $r): bool => $r->url() === 'https://sandbox.jazzcash.com.pk/ApplicationAPI/API/PaymentInquiry/Inquire'
        && $r['pp_TxnRefNo'] === $payment->reference
        && JazzCashHash::verify($r->data(), JC_SALT));
})->with([
    'paid' => ['000', '000', GatewayOutcome::Succeeded],
    'still pending' => ['000', '157', GatewayOutcome::Pending],
    'declined' => ['000', '999', GatewayOutcome::Failed],
    'inquiry itself failed — decide later' => ['110', '', GatewayOutcome::Pending],
]);

// ── refund ──────────────────────────────────────────────────────────────

it('explains that JazzCash wallet refunds are manual', function () {
    jazzcash()->refund(Payment::factory()->create(), '100.00');
})->throws(GatewayOperationNotSupported::class, 'manually');
