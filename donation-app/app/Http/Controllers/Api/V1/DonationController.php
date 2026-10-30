<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProofRequest;
use App\Http\Requests\Api\V1\StartDonationRequest;
use App\Http\Resources\V1\DonationResource;
use App\Models\Donation;
use App\Models\Payment;
use App\Payments\DTOs\CheckoutInstruction;
use App\Payments\Exceptions\GatewayNotAvailable;
use App\Services\DonationService;
use App\Services\PaymentService;
use App\Services\Site\WebDonorService;
use App\Settings\GatewaySettings;
use App\Support\ApiFormat;
use App\Support\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * @group Donate
 *
 * Giving from the app (Week 7 Day 4).
 *
 * 1. `POST /donations` → the donation, and what to do next (`next_step`):
 *    - `wait`: a wallet charge was sent — the donor approves it on their phone; poll `status_url`.
 *    - `open_checkout`: open `checkout` (the gateway's page) in a web view; then poll `status_url`.
 *    - `upload_proof`: bank transfer — show `bank`, then send the screenshot to `proof_url`.
 * 2. Poll `GET status_url` every few seconds until `is_final` is true.
 *
 * `status_url` and `proof_url` are signed links valid for 7 days — use them exactly as given.
 */
class DonationController extends Controller
{
    public const LINK_DAYS = 7;

    /** Ask the gateway ourselves when a payment is still open this long after it started. */
    private const CHECK_AFTER_SECONDS = 30;

    public function __construct(
        private readonly DonationService $donations,
        private readonly PaymentService $payments,
    ) {}

    /**
     * Start a donation
     *
     * Works with or without a donor token. Without one, `name` and `phone` are required and
     * the donor is matched by phone/email exactly like the website (an existing donor's
     * record is never changed from here). Send a fresh UUID as `Idempotency-Key` for every new
     * donation; sending the same key again returns the same donation (safe retries).
     *
     * @unauthenticated
     *
     * @response 201 {"success":true,"data":{"donation":{"number":"DN-2026-000123","status":{"value":"pending","label":"Pending"}},"next_step":"wait","payment":{"reference":"PAY8F3K2","status":{"value":"pending","label":"Pending"}},"checkout":{"type":"awaiting_donor","url":null,"fields":[],"message":"Approve the payment in your JazzCash app."},"status_url":"https://…/api/v1/donations/DN-2026-000123/status?expires=…&signature=…","proof_url":null,"bank":null},"message":"Donation started.","errors":null,"meta":{}}
     */
    public function store(StartDonationRequest $request, WebDonorService $donors): JsonResponse
    {
        $method = $request->paymentMethod();
        $donor = $request->donor();
        $notes = [];

        if ($donor === null) {
            ['donor' => $donor, 'notes' => $notes] = $donors->resolve([
                'name' => (string) $request->validated('name'),
                'phone' => (string) $request->validated('phone'),
                'email' => $request->optionalString('email'),
                'cnic' => $request->optionalString('cnic'),
                'whatsapp_opt_in' => (bool) $request->validated('whatsapp_opt_in'),
            ], app()->getLocale());
        }

        $campaign = $request->campaign();

        $donation = $this->donations->create([
            'donor_id' => $donor->id,
            'campaign_id' => $campaign?->id,
            'fund_id' => $campaign !== null ? null : $request->fund()?->id,
            'amount' => $request->amount(),
            'channel' => DonationChannel::Mobile,
            'payment_method' => $method,
            'is_anonymous' => (bool) $request->validated('is_anonymous'),
            'dedication' => $request->optionalString('dedication'),
            'staff_note' => $notes !== [] ? implode("\n", $notes) : null,
            'idempotency_key' => (string) $request->validated('idempotency_key'),
        ]);

        if ($method === PaymentMethod::BankTransfer) {
            return $this->started($donation, 'upload_proof');
        }

        if ($donation->status !== DonationStatus::Pending) {
            // A retry of a donation that has since been paid or closed.
            return $this->started($donation, $donation->status->isSettled() ? 'done' : 'closed', status: 200);
        }

        $open = $this->openPayment($donation);

        if ($open !== null) {
            // A retry while the first attempt is still running: don't charge twice.
            return $this->started($donation, 'wait', $open, CheckoutInstruction::awaitingDonor(__('This payment has already started. Please finish it, or wait a moment.')), 200);
        }

        $wallet = $request->optionalString('wallet_number');

        try {
            [$payment, $instruction] = $this->payments->initiate(
                $donation,
                $method === PaymentMethod::Easypaisa ? route('payments.easypaisa.return') : route('payments.jazzcash.return'),
                $wallet,
                $wallet !== null ? (string) $request->validated('cnic_last6') : null,
            );
        } catch (GatewayNotAvailable|DomainException $e) {
            $this->cancelUnlessPaying($donation, 'Payment could not start: '.$e->getMessage());

            return ApiResponse::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            report($e);
            $this->cancelUnlessPaying($donation, 'Payment could not start: gateway error');

            return ApiResponse::error(__('We could not reach :gateway just now. No money was taken. Please try again, or choose another way to pay.', ['gateway' => $method->label()]), 502);
        }

        $donation->refresh();
        $next = match (true) {
            $donation->status->isSettled() => 'done',
            $donation->status !== DonationStatus::Pending => 'closed',
            $instruction->url !== null => 'open_checkout',
            default => 'wait',
        };

        return $this->started($donation, $next, $payment, $instruction);
    }

    /**
     * Donation status
     *
     * Poll this (the signed `status_url`) every 5 seconds or so until `is_final` is true.
     * If the gateway's answer hasn't arrived after half a minute, we ask the gateway ourselves.
     *
     * @unauthenticated
     *
     * @urlParam number string required Example: DN-2026-000123
     */
    public function status(Request $request, string $number): JsonResponse
    {
        $donation = $this->findDonation($number);
        $payment = $this->latestPayment($donation);

        if ($payment !== null && $payment->status->isOpen() && $this->dueForCheck($payment)) {
            $payment = $this->payments->checkStatus($payment);
            $donation->refresh();
        }

        $donation->load(['fund', 'campaign', 'receipt']);
        $final = $donation->status !== DonationStatus::Pending;

        return ApiResponse::success([
            'donation' => DonationResource::make($donation)->resolve($request),
            'payment' => $this->paymentData($payment),
            'next_step' => $this->nextStep($donation),
            'is_final' => $final,
            'poll_after_seconds' => $final ? null : 5,
        ]);
    }

    /**
     * Upload transfer proof
     *
     * For a pending bank transfer / Raast donation: the transaction ID and a screenshot.
     * Finance checks it and the receipt follows. One proof per donation.
     *
     * @unauthenticated
     *
     * @urlParam number string required Example: DN-2026-000123
     */
    public function proof(ProofRequest $request, string $number): JsonResponse
    {
        $donation = $this->findDonation($number);

        if ($donation->payment_method !== PaymentMethod::BankTransfer || $donation->status !== DonationStatus::Pending) {
            return ApiResponse::error(__('This donation does not need a transfer proof.'), 422);
        }

        if ($donation->hasMedia(Donation::PROOF)) {
            return ApiResponse::error(__('We already have the proof for this donation. We will confirm it soon.'), 409);
        }

        $donation->reference = trim((string) $request->validated('reference'));
        $donation->save();

        $proof = $request->file('proof');

        if ($proof !== null) {
            $donation->addMedia($proof)->toMediaCollection(Donation::PROOF);
        }

        $donation->load(['fund', 'campaign', 'receipt']);

        return ApiResponse::success([
            'donation' => DonationResource::make($donation)->resolve($request),
            'next_step' => 'wait',
        ], __('Thank you — we will check your transfer and send your receipt, usually within one working day.'), 201);
    }

    public static function statusUrl(Donation $donation): string
    {
        return URL::temporarySignedRoute('api.v1.donations.status', now()->addDays(self::LINK_DAYS), ['number' => $donation->donation_no]);
    }

    public static function proofUrl(Donation $donation): string
    {
        return URL::temporarySignedRoute('api.v1.donations.proof', now()->addDays(self::LINK_DAYS), ['number' => $donation->donation_no]);
    }

    private function started(Donation $donation, string $next, ?Payment $payment = null, ?CheckoutInstruction $checkout = null, int $status = 201): JsonResponse
    {
        $donation->load(['fund', 'campaign', 'receipt']);
        $bank = app(GatewaySettings::class);
        $isBank = $donation->payment_method === PaymentMethod::BankTransfer;

        return ApiResponse::success([
            'donation' => DonationResource::make($donation)->resolve(request()),
            'next_step' => $isBank && $donation->hasMedia(Donation::PROOF) ? 'wait' : $next,
            'payment' => $this->paymentData($payment),
            'checkout' => $checkout?->toArray(),
            'status_url' => self::statusUrl($donation),
            'proof_url' => $isBank ? self::proofUrl($donation) : null,
            'bank' => $isBank ? [
                'bank_name' => $bank->bank_name,
                'account_title' => $bank->bank_account_title,
                'iban' => $bank->bank_iban,
                'raast_id' => $bank->bank_raast_id,
                'amount' => ApiFormat::money((string) $donation->amount),
                'reference_hint' => $donation->donation_no,
            ] : null,
        ], __('Donation started.'), $status);
    }

    /**
     * @return array{reference: string, status: array{value: string|int, label: string}|null, message: string|null}|null
     */
    private function paymentData(?Payment $payment): ?array
    {
        return $payment === null ? null : [
            'reference' => $payment->reference,
            'status' => ApiFormat::enum($payment->status),
            'message' => $payment->status === PaymentStatus::Failed ? $payment->response_message : null,
        ];
    }

    private function nextStep(Donation $donation): string
    {
        return match (true) {
            $donation->status->isSettled() => 'done',
            $donation->status !== DonationStatus::Pending => 'closed',
            $donation->payment_method === PaymentMethod::BankTransfer && ! $donation->hasMedia(Donation::PROOF) => 'upload_proof',
            default => 'wait',
        };
    }

    private function findDonation(string $number): Donation
    {
        return Donation::query()->where('donation_no', $number)->firstOrFail();
    }

    private function latestPayment(Donation $donation): ?Payment
    {
        return Payment::query()->where('donation_id', $donation->id)->latest('id')->first();
    }

    private function openPayment(Donation $donation): ?Payment
    {
        return Payment::query()
            ->where('donation_id', $donation->id)
            ->whereIn('status', [PaymentStatus::Initiated->value, PaymentStatus::Pending->value])
            ->latest('id')
            ->first();
    }

    private function dueForCheck(Payment $payment): bool
    {
        return $payment->initiated_at->lte(now()->subSeconds(self::CHECK_AFTER_SECONDS))
            && ($payment->last_checked_at === null || $payment->last_checked_at->lte(now()->subSeconds(self::CHECK_AFTER_SECONDS)));
    }

    private function cancelUnlessPaying(Donation $donation, string $reason): void
    {
        if ($this->openPayment($donation) === null) {
            $this->donations->cancel($donation, $reason);
        }
    }
}
