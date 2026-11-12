<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\WebhookOutcome;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Payments\DTOs\CallbackPayload;
use App\Payments\Exceptions\GatewayNotAvailable;
use App\Payments\Exceptions\InvalidGatewaySignature;
use App\Payments\GatewayRegistry;
use Throwable;

/**
 * Handles every gateway callback / return the same way (FR-PAY-02, CLAUDE.md rule 8):
 *
 *   1. store it word for word (committed before anything else can fail);
 *   2. let the gateway adapter verify it (hash / signature, or a status inquiry);
 *   3. find our payment by reference and apply the result through PaymentService
 *      (amount check, idempotency, needs-review rules all live there);
 *   4. record what happened on the log: processed, duplicate, rejected or error.
 *
 * Never throws for a bad or unknown callback — gateways and donors get a normal answer,
 * and the log says why it was rejected.
 */
class PaymentCallbackService
{
    public function __construct(
        private readonly GatewayRegistry $gateways,
        private readonly PaymentService $payments,
    ) {}

    public function handle(CallbackPayload $payload): PaymentWebhookLog
    {
        $log = PaymentWebhookLog::record($payload);

        try {
            $gateway = $this->gateways->get($payload->method);
            $result = $gateway->verifyCallback($payload);
        } catch (InvalidGatewaySignature|GatewayNotAvailable $e) {
            $log->signature_valid = $e instanceof InvalidGatewaySignature ? false : null;
            $log->markOutcome(WebhookOutcome::Rejected, error: $e->getMessage());

            return $log;
        } catch (Throwable $e) {
            report($e);
            $log->markOutcome(WebhookOutcome::Error, error: mb_substr($e->getMessage(), 0, 1000));

            return $log;
        }

        $log->signature_valid = true;
        $log->reference = mb_substr($result->reference, 0, 30);
        $log->gateway_ref = $result->gatewayRef !== null ? mb_substr($result->gatewayRef, 0, 100) : null;

        $payment = $this->payments->findByReference($result->reference);

        if ($payment === null || $payment->method !== $payload->method) {
            $log->markOutcome(WebhookOutcome::Rejected, error: "No {$payload->method->label()} payment with reference {$result->reference}.");

            return $log;
        }

        try {
            $before = $payment->status;
            $after = $this->payments->applyResult($payment, $result)->status;
        } catch (Throwable $e) {
            report($e);
            $log->markOutcome(WebhookOutcome::Error, $payment, mb_substr($e->getMessage(), 0, 1000));

            return $log;
        }

        $log->markOutcome($before === $after && ! $before->isOpen() ? WebhookOutcome::Duplicate : WebhookOutcome::Processed, $payment);

        return $log;
    }

    /** The payment a stored callback ended up matched to, if any. */
    public function paymentFor(PaymentWebhookLog $log): ?Payment
    {
        return $log->payment_id !== null ? Payment::query()->find($log->payment_id) : null;
    }

    /** Where each gateway sends the donor (and its result) back to us. */
    public static function returnUrl(PaymentMethod $method): string
    {
        return match ($method) {
            PaymentMethod::JazzCash => route('payments.jazzcash.return'),
            PaymentMethod::Easypaisa => route('payments.easypaisa.return'),
            default => throw new GatewayNotAvailable("{$method->label()} has no online return."),
        };
    }
}
