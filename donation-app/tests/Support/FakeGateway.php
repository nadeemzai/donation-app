<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\GatewayOutcome;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\DTOs\CallbackPayload;
use App\Payments\DTOs\CheckoutInstruction;
use App\Payments\DTOs\GatewayResult;
use App\Payments\DTOs\PaymentRequest;
use App\Payments\DTOs\RefundResult;
use App\Payments\Exceptions\GatewayOperationNotSupported;
use App\Payments\Exceptions\InvalidGatewaySignature;
use RuntimeException;

/**
 * A pretend gateway for tests. Callbacks are "signed" with an HMAC so signature
 * checking can be tested the same way as with real gateways.
 */
final class FakeGateway implements PaymentGateway
{
    public const SECRET = 'fake-secret';

    /** @var list<array{payment: string, request: PaymentRequest}> */
    public array $initiated = [];

    public bool $failToInitiate = false;

    public ?GatewayResult $nextStatus = null;

    public function __construct(
        private readonly PaymentMethod $method = PaymentMethod::JazzCash,
        public bool $enabled = true,
        public bool $configured = true,
        public int $order = 1,
    ) {}

    public function method(): PaymentMethod
    {
        return $this->method;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function mode(): string
    {
        return 'sandbox';
    }

    public function displayOrder(): int
    {
        return $this->order;
    }

    public function initiate(Payment $payment, PaymentRequest $request): CheckoutInstruction
    {
        if ($this->failToInitiate) {
            throw new RuntimeException('Gateway timed out');
        }

        $this->initiated[] = ['payment' => $payment->reference, 'request' => $request];

        return CheckoutInstruction::redirect("https://fake-gateway.test/pay?ref={$payment->reference}");
    }

    /**
     * Fields a real callback would carry, correctly signed.
     *
     * @return array<string, string>
     */
    public static function callbackFields(Payment $payment, string $status = 'succeeded', ?string $amount = null, string $gatewayRef = 'FG-1001'): array
    {
        $amount ??= (string) $payment->amount;
        $fields = ['reference' => $payment->reference, 'status' => $status, 'amount' => $amount, 'gateway_ref' => $gatewayRef];
        $fields['signature'] = self::sign($fields);

        return $fields;
    }

    /**
     * @param  array<string, string>  $fields
     */
    public static function sign(array $fields): string
    {
        return hash_hmac('sha256', implode('|', [$fields['reference'], $fields['status'], $fields['amount'], $fields['gateway_ref']]), self::SECRET);
    }

    public function verifyCallback(CallbackPayload $payload): GatewayResult
    {
        $fields = [
            'reference' => (string) $payload->field('reference'),
            'status' => (string) $payload->field('status'),
            'amount' => (string) $payload->field('amount'),
            'gateway_ref' => (string) $payload->field('gateway_ref'),
        ];

        if (! hash_equals(self::sign($fields), (string) $payload->field('signature'))) {
            throw new InvalidGatewaySignature('Signature does not match.');
        }

        return new GatewayResult(
            outcome: GatewayOutcome::from($fields['status']),
            reference: $fields['reference'],
            gatewayRef: $fields['gateway_ref'],
            amount: $fields['amount'],
            currency: 'PKR',
            responseCode: $fields['status'] === 'succeeded' ? '000' : '999',
            message: $fields['status'] === 'succeeded' ? 'Paid' : 'Declined',
            raw: $fields,
        );
    }

    public function queryStatus(Payment $payment): GatewayResult
    {
        return $this->nextStatus ?? new GatewayResult(GatewayOutcome::Pending, $payment->reference);
    }

    public function refund(Payment $payment, string $amount): RefundResult
    {
        throw new GatewayOperationNotSupported('The fake gateway has no refund API.');
    }

    /** A verified success answer for a payment (optionally with a different amount / gateway ref). */
    public static function success(Payment $payment, ?string $amount = null, string $gatewayRef = 'FG-1001'): GatewayResult
    {
        return new GatewayResult(GatewayOutcome::Succeeded, $payment->reference, $gatewayRef, $amount ?? (string) $payment->amount, 'PKR', '000', 'Paid');
    }

    public static function failure(Payment $payment): GatewayResult
    {
        return new GatewayResult(GatewayOutcome::Failed, $payment->reference, null, null, null, '999', 'Declined by wallet');
    }
}
