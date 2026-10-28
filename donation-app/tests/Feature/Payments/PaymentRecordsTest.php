<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\WebhookOutcome;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Payments\DTOs\CallbackPayload;
use App\Payments\Exceptions\InvalidGatewaySignature;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\Support\FakeGateway;

// FR-PAY-02, CLAUDE.md rule 8 — payment records and raw callback logs

it('never changes a payment\'s amount, donation or reference', function (string $field, mixed $value) {
    Payment::factory()->create()->update([$field => $value]);
})->with([
    'amount' => ['amount', '1.00'],
    'reference' => ['reference', 'T999999999999ZZZZZ'],
    'method' => ['method', PaymentMethod::Easypaisa],
])->throws(DomainException::class, 'can never change');

it('never deletes a payment', function () {
    Payment::factory()->create()->delete();
})->throws(DomainException::class, 'never deleted');

it('never stores the same gateway transaction on two payments', function () {
    $a = Payment::factory()->create();
    $b = Payment::factory()->create();

    $a->forceFill(['gateway_ref' => 'JC-1'])->save();
    $b->forceFill(['gateway_ref' => 'JC-1'])->save();
})->throws(UniqueConstraintViolationException::class);

it('stores a callback exactly as it arrived, without auth or cookie headers', function () {
    $payload = new CallbackPayload(
        method: PaymentMethod::JazzCash,
        fields: ['pp_TxnRefNo' => 'T261116101010ABCDE', 'pp_ResponseCode' => '000'],
        rawBody: 'pp_TxnRefNo=T261116101010ABCDE&pp_ResponseCode=000',
        headers: ['Content-Type' => ['application/x-www-form-urlencoded'], 'Cookie' => ['session=secret'], 'Authorization' => ['Bearer x']],
        url: 'https://example.test/payments/jazzcash/callback',
        ip: '203.0.113.9',
    );

    $log = PaymentWebhookLog::record($payload)->fresh();

    expect($log->payload)->toBe('pp_TxnRefNo=T261116101010ABCDE&pp_ResponseCode=000')
        ->and($log->fields)->toBe(['pp_TxnRefNo' => 'T261116101010ABCDE', 'pp_ResponseCode' => '000'])
        ->and($log->headers)->toHaveKey('content-type')
        ->and($log->headers)->not->toHaveKey('cookie')
        ->and($log->headers)->not->toHaveKey('authorization')
        ->and($log->ip)->toBe('203.0.113.9')
        ->and($log->outcome)->toBe(WebhookOutcome::Received);
});

it('records what happened to a callback but never changes what arrived', function () {
    $log = PaymentWebhookLog::factory()->create();
    $payment = Payment::factory()->create();

    $log->markOutcome(WebhookOutcome::Processed, $payment);
    expect($log->fresh())
        ->outcome->toBe(WebhookOutcome::Processed)
        ->payment_id->toBe($payment->id)
        ->processed_at->not->toBeNull();

    $log->update(['payload' => 'tampered']);
})->throws(DomainException::class, 'can never be changed');

it('never deletes a callback log', function () {
    PaymentWebhookLog::factory()->create()->delete();
})->throws(DomainException::class, 'never deleted');

it('rejects a callback whose signature does not match (fake gateway contract)', function () {
    $payment = Payment::factory()->create();
    $fields = FakeGateway::callbackFields($payment);
    $fields['amount'] = '1.00'; // tampered after signing

    (new FakeGateway)->verifyCallback(new CallbackPayload(PaymentMethod::JazzCash, $fields, http_build_query($fields)));
})->throws(InvalidGatewaySignature::class);

it('accepts a correctly signed callback', function () {
    $payment = Payment::factory()->create();
    $fields = FakeGateway::callbackFields($payment, gatewayRef: 'FG-42');

    $result = (new FakeGateway)->verifyCallback(new CallbackPayload(PaymentMethod::JazzCash, $fields, http_build_query($fields)));

    expect($result->reference)->toBe($payment->reference)
        ->and($result->gatewayRef)->toBe('FG-42')
        ->and($result->amount)->toBe((string) $payment->amount);
});
