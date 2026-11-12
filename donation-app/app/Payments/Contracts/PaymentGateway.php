<?php

declare(strict_types=1);

namespace App\Payments\Contracts;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Payments\DTOs\CallbackPayload;
use App\Payments\DTOs\CheckoutInstruction;
use App\Payments\DTOs\GatewayResult;
use App\Payments\DTOs\PaymentRequest;
use App\Payments\DTOs\RefundResult;
use App\Payments\Exceptions\GatewayOperationNotSupported;
use App\Payments\Exceptions\InvalidGatewaySignature;

/**
 * Every payment gateway (JazzCash, Easypaisa; card PSP in Phase 2) implements this
 * (FR-PAY-01). Adapters only talk to the gateway and translate its answers; they never
 * change payments, donations or balances — PaymentService does that.
 *
 * Adapters read their credentials from GatewaySettings and must never log or return
 * secrets (passwords, salts, hash keys).
 */
interface PaymentGateway
{
    public function method(): PaymentMethod;

    /** Switched on in Settings → Gateways. */
    public function isEnabled(): bool;

    /** All credentials needed for the current mode are filled in. */
    public function isConfigured(): bool;

    /** "sandbox" or "live". */
    public function mode(): string;

    /** Position in the donor's list of payment options (lower first). */
    public function displayOrder(): int;

    /**
     * Start a payment. Returns what the website/app must do next (redirect, auto-post
     * a form, or wait for the donor to approve on their phone).
     */
    public function initiate(Payment $payment, PaymentRequest $request): CheckoutInstruction;

    /**
     * Check the callback's hash/signature and translate it.
     *
     * @throws InvalidGatewaySignature when the payload wasn't sent by the gateway
     */
    public function verifyCallback(CallbackPayload $payload): GatewayResult;

    /** Ask the gateway for the payment's current status (status inquiry API). */
    public function queryStatus(Payment $payment): GatewayResult;

    /**
     * @throws GatewayOperationNotSupported when the gateway has no refund API
     */
    public function refund(Payment $payment, string $amount): RefundResult;
}
