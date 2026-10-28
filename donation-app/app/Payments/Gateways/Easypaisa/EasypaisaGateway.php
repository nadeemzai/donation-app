<?php

declare(strict_types=1);

namespace App\Payments\Gateways\Easypaisa;

use App\Enums\CheckoutType;
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
use App\Settings\GatewaySettings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Easypaisa (FR-PAY-01). Same two ways to pay as JazzCash:
 *
 * - Hosted checkout (website), two steps:
 *     1. the browser auto-posts a signed form to Index.jsf; Easypaisa sends the donor back
 *        to our postBackURL with an auth_token;
 *     2. we auto-post that auth_token to Confirm.jsf (confirmStep()); the donor pays and is
 *        sent back with status / desc / orderRefNumber.
 *   That final redirect is NOT signed, so verifyCallback() never trusts it: it asks the
 *   status inquiry API (authenticated, server to server) for the real result.
 * - Direct wallet (mobile app): REST initiate-ma-transaction; the donor approves in the
 *   Easypaisa app while the call waits.
 *
 * Easypaisa has no public refund API: refunds are manual and recorded with
 * DonationService::refund().
 *
 * ┌─ CHECK DURING THE FIRST SANDBOX RUN ───────────────────────────────────────────┐
 * │ URLs, the hosted amount format and the status words below follow Easypaisa's  │
 * │ merchant integration guides and common integrations; the merchant kit is not  │
 * │ public. Confirm them with your kit and adjust only these constants.           │
 * └──────────────────────────────────────────────────────────────────────────────┘
 */
final class EasypaisaGateway implements PaymentGateway
{
    public const HOSTS = [
        'sandbox' => 'https://easypaystg.easypaisa.com.pk',
        'live' => 'https://easypay.easypaisa.com.pk',
    ];

    public const HOSTED_PATH = '/easypay/Index.jsf';

    public const CONFIRM_PATH = '/easypay/Confirm.jsf';

    public const WALLET_PATH = '/easypay-service/rest/v4/initiate-ma-transaction';

    public const INQUIRY_PATH = '/easypay-service/rest/v4/inquire-transaction';

    /** REST responseCode for "done". */
    public const OK_CODE = '0000';

    /** Status inquiry transactionStatus values. Anything else = failed. */
    public const PAID_STATUSES = ['PAID'];

    public const PENDING_STATUSES = ['PENDING', 'INITIATED'];

    /** Time the donor has on Easypaisa's side to finish — inside PaymentService::GIVE_UP_AFTER_MINUTES. */
    private const EXPIRY_HOURS = 1;

    public function method(): PaymentMethod
    {
        return PaymentMethod::Easypaisa;
    }

    public function isEnabled(): bool
    {
        return $this->settings()->easypaisa_enabled;
    }

    /** Store ID + hash key (hosted page) and API login + account number (wallet and status checks). */
    public function isConfigured(): bool
    {
        $s = $this->settings();

        return filled($s->easypaisa_store_id) && strlen((string) $s->easypaisa_hash_key) === 16
            && filled($s->easypaisa_username) && filled($s->easypaisa_password) && filled($s->easypaisa_account_number);
    }

    public function mode(): string
    {
        return $this->settings()->easypaisa_mode === 'live' ? 'live' : 'sandbox';
    }

    public function displayOrder(): int
    {
        return $this->settings()->easypaisa_display_order;
    }

    public function initiate(Payment $payment, PaymentRequest $request): CheckoutInstruction
    {
        if ($request->directWallet) {
            return $this->chargeWallet($payment, $request);
        }

        $now = now('Asia/Karachi');
        $fields = [
            'storeId' => (string) $this->settings()->easypaisa_store_id,
            'amount' => self::hostedAmount((string) $payment->amount),
            'postBackURL' => $request->returnUrl,
            'orderRefNum' => $payment->reference,
            'expiryDate' => $now->copy()->addHours(self::EXPIRY_HOURS)->format('Ymd His'),
            'autoRedirect' => '1',
            'paymentMethod' => 'MA_PAYMENT_METHOD',
            'emailAddr' => (string) $request->email,
            'mobileNum' => (string) $request->mobileNumber,
            'timeStamp' => $now->format('Y-m-d\TH:i:s'),
        ];
        $fields['merchantHashedReq'] = EasypaisaHash::make($fields, (string) $this->settings()->easypaisa_hash_key);

        return CheckoutInstruction::formPost($this->host().self::HOSTED_PATH, $fields);
    }

    /**
     * Hosted checkout step 2: Easypaisa sent the donor back with auth_token; post it to
     * Confirm.jsf. $finalReturnUrl receives the final status redirect.
     */
    public function confirmStep(string $authToken, string $finalReturnUrl): CheckoutInstruction
    {
        return new CheckoutInstruction(CheckoutType::FormPost, $this->host().self::CONFIRM_PATH, [
            'auth_token' => $authToken,
            'postBackURL' => $finalReturnUrl,
        ]);
    }

    /**
     * The final redirect only tells us WHICH payment to check; the status inquiry API
     * gives the result. A redirect without an order reference is rejected.
     */
    public function verifyCallback(CallbackPayload $payload): GatewayResult
    {
        $reference = $payload->field('orderRefNumber') ?? $payload->field('orderRefNum');

        if ($reference === null || preg_match('/^[A-Za-z0-9]{1,30}$/', $reference) !== 1) {
            throw new InvalidGatewaySignature('Easypaisa return has no valid order reference.');
        }

        return $this->inquire($reference);
    }

    public function queryStatus(Payment $payment): GatewayResult
    {
        return $this->inquire($payment->reference);
    }

    public function refund(Payment $payment, string $amount): RefundResult
    {
        throw new GatewayOperationNotSupported('Easypaisa payments cannot be refunded through the API. Send the money back manually and record the refund on the donation.');
    }

    private function chargeWallet(Payment $payment, PaymentRequest $request): CheckoutInstruction
    {
        $amount = bcadd((string) $payment->amount, '0', 2);

        // The donor approves in the Easypaisa app while this call waits.
        $response = $this->post(self::WALLET_PATH, [
            'orderId' => $payment->reference,
            'storeId' => (string) $this->settings()->easypaisa_store_id,
            'transactionAmount' => $amount,
            'transactionType' => 'MA',
            'mobileAccountNo' => (string) $request->mobileNumber,
            'emailAddress' => (string) $request->email,
        ], timeout: 90);

        $code = self::str($response['responseCode'] ?? null);

        // This is the answer to our own authenticated call, so the amount we asked for is
        // the amount charged unless Easypaisa reports another one.
        $result = new GatewayResult(
            outcome: $code === self::OK_CODE ? GatewayOutcome::Succeeded : GatewayOutcome::Failed,
            reference: self::str($response['orderId'] ?? null) ?? $payment->reference,
            gatewayRef: self::str($response['transactionId'] ?? null),
            amount: self::amount($response['transactionAmount'] ?? null) ?? $amount,
            currency: 'PKR',
            responseCode: $code,
            message: self::str($response['responseDesc'] ?? null),
            raw: $response,
        );

        return CheckoutInstruction::awaitingDonor('Approve the payment in your Easypaisa app.', $result);
    }

    private function inquire(string $reference): GatewayResult
    {
        $response = $this->post(self::INQUIRY_PATH, [
            'orderId' => $reference,
            'storeId' => (string) $this->settings()->easypaisa_store_id,
            'accountNum' => (string) $this->settings()->easypaisa_account_number,
        ], timeout: 30);

        $code = self::str($response['responseCode'] ?? null);
        $status = strtoupper(self::str($response['transactionStatus'] ?? null) ?? '');

        // The inquiry itself failed (e.g. order not found yet): decide later, never fail.
        if ($code !== self::OK_CODE) {
            return new GatewayResult(
                outcome: GatewayOutcome::Pending,
                reference: $reference,
                responseCode: $code,
                message: 'Status inquiry: '.(self::str($response['responseDesc'] ?? null) ?? 'no answer'),
                raw: $response,
            );
        }

        return new GatewayResult(
            outcome: match (true) {
                in_array($status, self::PAID_STATUSES, true) => GatewayOutcome::Succeeded,
                in_array($status, self::PENDING_STATUSES, true) => GatewayOutcome::Pending,
                default => GatewayOutcome::Failed,
            },
            reference: self::str($response['orderId'] ?? null) ?? $reference,
            gatewayRef: self::str($response['transactionId'] ?? null),
            amount: self::amount($response['transactionAmount'] ?? null),
            currency: 'PKR',
            responseCode: $status !== '' ? $status : $code,
            message: self::str($response['responseDesc'] ?? null),
            raw: $response,
        );
    }

    /**
     * POST JSON with the Credentials header (base64 "username:password").
     *
     * @param  array<string, string>  $fields
     * @return array<string, mixed>
     */
    private function post(string $path, array $fields, int $timeout): array
    {
        $s = $this->settings();

        $json = Http::asJson()
            ->acceptJson()
            ->withHeaders(['Credentials' => base64_encode($s->easypaisa_username.':'.$s->easypaisa_password)])
            ->timeout($timeout)
            ->post($this->host().$path, $fields)
            ->throw()
            ->json();

        if (! is_array($json)) {
            throw new RuntimeException('Easypaisa returned an unreadable answer.');
        }

        $data = [];
        foreach ($json as $key => $value) {
            $data[(string) $key] = $value;
        }

        return $data;
    }

    /** Hosted page amount: "2500.0", "2500.5", "2500.55" — the exact value, at least one decimal. */
    private static function hostedAmount(string $amount): string
    {
        $trimmed = rtrim(rtrim(bcadd($amount, '0', 2), '0'), '.');

        return str_contains($trimmed, '.') ? $trimmed : $trimmed.'.0';
    }

    private static function amount(mixed $value): ?string
    {
        $value = self::str($value);

        return $value !== null && preg_match('/^\d{1,12}(\.\d{1,2})?$/', $value) === 1 ? bcadd($value, '0', 2) : null;
    }

    private static function str(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    private function host(): string
    {
        return self::HOSTS[$this->mode()];
    }

    private function settings(): GatewaySettings
    {
        return app(GatewaySettings::class);
    }
}
