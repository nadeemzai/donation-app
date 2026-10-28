<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DonationStatus;
use App\Enums\GatewayOutcome;
use App\Enums\PaymentStatus;
use App\Models\Donation;
use App\Models\Payment;
use App\Payments\DTOs\CheckoutInstruction;
use App\Payments\DTOs\GatewayResult;
use App\Payments\DTOs\PaymentRequest;
use App\Payments\GatewayRegistry;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Gateway payments (FR-PAY-01/02, BR-03). The only place a Payment changes status.
 *
 *   initiate()    → creates a Payment, asks the gateway to start it
 *   applyResult() → applies a VERIFIED gateway answer (callback or status check):
 *                   success confirms the donation through DonationService, failure fails it
 *
 * applyResult() is safe to call any number of times with the same answer (replayed
 * webhooks). Anything unusual — wrong amount, a gateway transaction id seen before,
 * money taken after we gave up, a donation already paid — goes to "needs review"
 * instead of being guessed at. Lock order: payment → donation → fund → campaign.
 */
class PaymentService
{
    /** How long a donor has to finish paying before the payment is checked and expired (Day 5). */
    public const EXPIRES_AFTER_MINUTES = 30;

    public function __construct(
        private readonly GatewayRegistry $gateways,
        private readonly DonationService $donations,
    ) {}

    /**
     * Start paying a pending gateway donation.
     *
     * The gateway is called OUTSIDE any database transaction (it is a network call);
     * if it fails the payment is marked failed and the error is re-thrown.
     *
     * @return array{0: Payment, 1: CheckoutInstruction}
     */
    public function initiate(Donation $donation, string $returnUrl): array
    {
        $method = $donation->payment_method;

        if (! $method->isGateway()) {
            throw new DomainException("{$method->label()} donations are not paid online.");
        }

        if ($donation->status !== DonationStatus::Pending) {
            throw new DomainException("Donation {$donation->donation_no} is {$donation->status->label()}, so it cannot be paid.");
        }

        $gateway = $this->gateways->forCheckout($method);

        $payment = Payment::query()->create([
            'donation_id' => $donation->id,
            'method' => $method,
            'amount' => (string) $donation->amount,
            'currency' => $donation->currency,
            'mode' => $gateway->mode(),
            'expires_at' => now()->addMinutes(self::EXPIRES_AFTER_MINUTES),
        ]);

        $request = new PaymentRequest(
            amount: (string) $donation->amount,
            currency: $donation->currency,
            description: "Donation {$donation->donation_no}",
            returnUrl: $returnUrl,
            mobileNumber: $donation->donor->phone,
            email: $donation->donor->email,
        );

        try {
            $instruction = $gateway->initiate($payment, $request);
        } catch (Throwable $e) {
            $this->move($payment, PaymentStatus::Failed, message: 'Could not start payment: '.mb_substr($e->getMessage(), 0, 200));

            throw $e;
        }

        $this->move($payment, PaymentStatus::Pending);

        return [$payment, $instruction];
    }

    /**
     * Apply a gateway answer whose signature/hash has ALREADY been verified by the adapter.
     */
    public function applyResult(Payment $payment, GatewayResult $result): Payment
    {
        return DB::transaction(function () use ($payment, $result): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($result->reference !== $locked->reference) {
                throw new DomainException("Gateway answer is for payment {$result->reference}, not {$locked->reference}.");
            }

            return match ($result->outcome) {
                GatewayOutcome::Pending => $this->stillPending($locked, $result),
                GatewayOutcome::Failed => $this->failed($locked, $result),
                GatewayOutcome::Succeeded => $this->succeeded($locked, $result),
            };
        });
    }

    public function findByReference(string $reference): ?Payment
    {
        return Payment::query()->where('reference', $reference)->first();
    }

    private function stillPending(Payment $payment, GatewayResult $result): Payment
    {
        if ($payment->status === PaymentStatus::Initiated) {
            $this->move($payment, PaymentStatus::Pending, $result);
        }

        return $payment;
    }

    private function failed(Payment $payment, GatewayResult $result): Payment
    {
        if (! $payment->status->isOpen()) {
            return $payment; // already decided — a replay
        }

        $this->move($payment, PaymentStatus::Failed, $result);

        // The donation fails too, unless the donor already started another attempt.
        $donation = $payment->donation;
        $otherOpen = Payment::query()
            ->where('donation_id', $donation->id)
            ->whereKeyNot($payment->getKey())
            ->whereIn('status', [PaymentStatus::Initiated->value, PaymentStatus::Pending->value])
            ->exists();

        if ($donation->status === DonationStatus::Pending && ! $otherOpen) {
            $this->donations->fail($donation, $result->message ?? 'Payment failed at the gateway');
        }

        return $payment;
    }

    private function succeeded(Payment $payment, GatewayResult $result): Payment
    {
        if ($payment->status === PaymentStatus::Succeeded || $payment->status === PaymentStatus::NeedsReview) {
            return $payment; // already decided — a replay
        }

        if ($result->gatewayRef !== null) {
            $usedBy = Payment::query()->where('gateway_ref', $result->gatewayRef)->whereKeyNot($payment->getKey())->value('reference');

            if ($usedBy !== null) {
                return $this->review($payment, $result, "Gateway transaction {$result->gatewayRef} was already applied to payment {$usedBy}.", keepGatewayRef: false);
            }
        }

        if (! $payment->status->isOpen()) {
            return $this->review($payment, $result, "Gateway reported success after the payment was marked {$payment->status->label()}. Check whether the donor was charged.");
        }

        if ($result->amount === null || bccomp($result->amount, (string) $payment->amount, 2) !== 0
            || ($result->currency !== null && strtoupper($result->currency) !== $payment->currency)) {
            return $this->review($payment, $result, sprintf(
                'Gateway says %s %s was paid; expected %s %s.',
                $result->currency ?? '?', $result->amount ?? '?', $payment->currency, $payment->amount,
            ));
        }

        $donation = Donation::query()->findOrFail($payment->donation_id);

        if ($donation->status->isSettled()) {
            return $this->review($payment, $result, "Donation {$donation->donation_no} was already paid by another payment. The donor may have paid twice — refund one.");
        }

        if ($donation->status !== DonationStatus::Pending) {
            return $this->review($payment, $result, "Donation {$donation->donation_no} was {$donation->status->label()} before the gateway confirmed the payment.");
        }

        $this->move($payment, PaymentStatus::Succeeded, $result);
        $this->donations->confirm($donation, null, $result->gatewayRef ?? $payment->reference, now());

        return $payment;
    }

    private function review(Payment $payment, GatewayResult $result, string $reason, bool $keepGatewayRef = true): Payment
    {
        $payment->review_reason = mb_substr($reason, 0, 255);
        $this->move($payment, PaymentStatus::NeedsReview, $keepGatewayRef ? $result : null, $result->responseCode, $result->message);

        activity('audit')
            ->performedOn($payment)
            ->event('needs_review')
            ->withProperties(['reason' => $reason, 'gateway_ref' => $result->gatewayRef, 'amount' => $result->amount])
            ->log("Payment {$payment->reference} needs review: {$reason}");

        return $payment;
    }

    private function move(Payment $payment, PaymentStatus $next, ?GatewayResult $result = null, ?string $code = null, ?string $message = null): void
    {
        if (! $payment->status->canTransitionTo($next)) {
            throw new DomainException("A payment that is {$payment->status->label()} cannot become {$next->label()}.");
        }

        $payment->status = $next;

        if ($result?->gatewayRef !== null) {
            $payment->gateway_ref = $result->gatewayRef;
        }

        $payment->response_code = $result->responseCode ?? $code ?? $payment->response_code;
        $payment->response_message = mb_substr($result->message ?? $message ?? (string) $payment->response_message, 0, 255) ?: null;

        if (! $next->isOpen()) {
            $payment->completed_at ??= now();
        }

        $payment->save();
    }
}
