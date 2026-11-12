<?php

declare(strict_types=1);

namespace App\Payments\Gateways\JazzCash;

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
 * JazzCash (FR-PAY-01). Two ways to pay, sharing the same hash and status check:
 *
 * - Hosted page (website): the donor's browser auto-posts a signed form to JazzCash's
 *   "merchantform" page; JazzCash posts the result back to pp_ReturnURL (our callback).
 * - Direct wallet, MWALLET REST v2.0 (mobile app): we send the donor's wallet number and
 *   the last 6 digits of their CNIC; the donor approves on their phone and the answer
 *   comes back in the same call (or "pending" if they haven't approved yet).
 *
 * Status inquiry (PaymentInquiry/Inquire) settles anything still pending (Day 5 job).
 * JazzCash has no refund API for wallet payments: refunds are made manually and
 * recorded with DonationService::refund().
 *
 * ┌─ CHECK DURING THE FIRST SANDBOX RUN ───────────────────────────────────────────┐
 * │ URLs and response codes below follow JazzCash's published sandbox docs and   │
 * │ common integrations. JazzCash does not publish a full code list — confirm    │
 * │ them in the merchant portal and adjust only these constants.                 │
 * └──────────────────────────────────────────────────────────────────────────────┘
 *
 * Note: JazzCash's hosted-page design puts pp_Password in the browser form. That is how
 * JazzCash works; the integrity salt (which signs everything) never leaves the server.
 */
final class JazzCashGateway implements PaymentGateway
{
    public const HOSTS = [
        'sandbox' => 'https://sandbox.jazzcash.com.pk',
        'live' => 'https://payments.jazzcash.com.pk',
    ];

    public const HOSTED_PATH = '/CustomerPortal/transactionmanagement/merchantform/';

    public const WALLET_PATH = '/ApplicationAPI/API/2.0/Purchase/DoMWalletTransaction';

    public const INQUIRY_PATH = '/ApplicationAPI/API/PaymentInquiry/Inquire';

    /** pp_ResponseCode / pp_PaymentResponseCode values. Anything else = failed. */
    public const SUCCESS_CODES = ['000'];

    public const PENDING_CODES = ['124', '157'];

    /** Never kept in GatewayResult::$raw (which may be logged). */
    private const SECRET_FIELDS = ['pp_Password', 'pp_SecureHash', 'pp_CNIC'];

    /** Time the donor has on JazzCash's side to finish — inside PaymentService::GIVE_UP_AFTER_MINUTES. */
    private const EXPIRY_HOURS = 1;

    public function method(): PaymentMethod
    {
        return PaymentMethod::JazzCash;
    }

    public function isEnabled(): bool
    {
        return $this->settings()->jazzcash_enabled;
    }

    public function isConfigured(): bool
    {
        $s = $this->settings();

        return filled($s->jazzcash_merchant_id) && filled($s->jazzcash_password) && filled($s->jazzcash_integrity_salt);
    }

    public function mode(): string
    {
        return $this->settings()->jazzcash_mode === 'live' ? 'live' : 'sandbox';
    }

    public function displayOrder(): int
    {
        return $this->settings()->jazzcash_display_order;
    }

    public function initiate(Payment $payment, PaymentRequest $request): CheckoutInstruction
    {
        $fields = $this->baseFields($payment, $request);

        if ($request->directWallet) {
            return $this->chargeWallet($payment, $request, $fields);
        }

        $fields += [
            'pp_Version' => '1.1',
            'pp_TxnType' => 'MWALLET',
            'pp_BankID' => '',
            'pp_ProductID' => '',
            'pp_ReturnURL' => $request->returnUrl,
        ];
        $fields['pp_SecureHash'] = JazzCashHash::make($fields, $this->salt());

        return CheckoutInstruction::formPost($this->host().self::HOSTED_PATH, $fields);
    }

    public function verifyCallback(CallbackPayload $payload): GatewayResult
    {
        if (! JazzCashHash::verify($payload->fields, $this->salt())) {
            throw new InvalidGatewaySignature('JazzCash callback hash does not match.');
        }

        return $this->toResult($payload->fields, (string) $payload->field('pp_TxnRefNo'), 'pp_ResponseCode');
    }

    public function queryStatus(Payment $payment): GatewayResult
    {
        $fields = [
            'pp_TxnRefNo' => $payment->reference,
            'pp_MerchantID' => (string) $this->settings()->jazzcash_merchant_id,
            'pp_Password' => (string) $this->settings()->jazzcash_password,
        ];
        $fields['pp_SecureHash'] = JazzCashHash::make($fields, $this->salt());

        $response = $this->post(self::INQUIRY_PATH, $fields, timeout: 30);

        // pp_ResponseCode is about the inquiry itself; pp_PaymentResponseCode is the payment.
        if ((string) ($response['pp_ResponseCode'] ?? '') !== '000') {
            return new GatewayResult(
                outcome: GatewayOutcome::Pending,
                reference: $payment->reference,
                responseCode: self::str($response['pp_ResponseCode'] ?? null),
                message: 'Status inquiry: '.(self::str($response['pp_ResponseMessage'] ?? null) ?? 'no answer'),
                raw: $this->withoutSecrets($response),
            );
        }

        return $this->toResult($response, $payment->reference, 'pp_PaymentResponseCode');
    }

    public function refund(Payment $payment, string $amount): RefundResult
    {
        throw new GatewayOperationNotSupported('JazzCash wallet payments cannot be refunded through the API. Send the money back manually and record the refund on the donation.');
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function chargeWallet(Payment $payment, PaymentRequest $request, array $fields): CheckoutInstruction
    {
        $fields += [
            'pp_MobileNumber' => (string) $request->mobileNumber,
            'pp_CNIC' => (string) $request->cnicLast6,
            'pp_DiscountedAmount' => '',
        ];
        $fields['pp_SecureHash'] = JazzCashHash::make($fields, $this->salt());

        // The donor approves on their phone while this call waits, so allow a long timeout.
        $response = $this->post(self::WALLET_PATH, $fields, timeout: 90);

        return CheckoutInstruction::awaitingDonor(
            'Approve the payment in your JazzCash app or on the prompt on your phone.',
            $this->toResult($response, $payment->reference, 'pp_ResponseCode'),
        );
    }

    /**
     * @return array<string, string>
     */
    private function baseFields(Payment $payment, PaymentRequest $request): array
    {
        $now = now('Asia/Karachi');
        $s = $this->settings();

        return [
            'pp_Language' => 'EN',
            'pp_MerchantID' => (string) $s->jazzcash_merchant_id,
            'pp_SubMerchantID' => '',
            'pp_Password' => (string) $s->jazzcash_password,
            'pp_TxnRefNo' => $payment->reference,
            'pp_Amount' => bcmul((string) $payment->amount, '100', 0), // paisa
            'pp_TxnCurrency' => 'PKR',
            'pp_TxnDateTime' => $now->format('YmdHis'),
            'pp_BillReference' => (string) preg_replace('/[^A-Za-z0-9]/', '', $payment->donation->donation_no),
            'pp_Description' => mb_substr((string) preg_replace('/[^A-Za-z0-9 .\-]/', '', $request->description), 0, 100),
            'pp_TxnExpiryDateTime' => $now->copy()->addHours(self::EXPIRY_HOURS)->format('YmdHis'),
            'ppmpf_1' => '',
            'ppmpf_2' => '',
            'ppmpf_3' => '',
            'ppmpf_4' => '',
            'ppmpf_5' => '',
        ];
    }

    /**
     * POST JSON to a JazzCash API and check the hash on the answer (when it has one).
     *
     * @param  array<string, string>  $fields
     * @return array<string, mixed>
     */
    private function post(string $path, array $fields, int $timeout): array
    {
        $response = Http::asJson()->acceptJson()->timeout($timeout)->post($this->host().$path, $fields)->throw();

        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException('JazzCash returned an unreadable answer.');
        }

        $data = [];
        foreach ($json as $key => $value) {
            $data[(string) $key] = $value;
        }

        if (isset($data['pp_SecureHash']) && $data['pp_SecureHash'] !== '' && ! JazzCashHash::verify($data, $this->salt())) {
            throw new InvalidGatewaySignature('JazzCash response hash does not match.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function toResult(array $fields, string $reference, string $codeField): GatewayResult
    {
        $code = self::str($fields[$codeField] ?? null) ?? '';
        $paisa = self::str($fields['pp_Amount'] ?? null);

        return new GatewayResult(
            outcome: match (true) {
                in_array($code, self::SUCCESS_CODES, true) => GatewayOutcome::Succeeded,
                in_array($code, self::PENDING_CODES, true) => GatewayOutcome::Pending,
                default => GatewayOutcome::Failed,
            },
            reference: self::str($fields['pp_TxnRefNo'] ?? null) ?? $reference,
            gatewayRef: self::str($fields['pp_RetreivalReferenceNo'] ?? null),
            amount: $paisa !== null && ctype_digit($paisa) ? bcdiv($paisa, '100', 2) : null,
            currency: self::str($fields['pp_TxnCurrency'] ?? null) ?? 'PKR',
            responseCode: $code !== '' ? $code : null,
            message: self::str($fields['pp_ResponseMessage'] ?? null),
            raw: $this->withoutSecrets($fields),
        );
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function withoutSecrets(array $fields): array
    {
        return array_diff_key($fields, array_flip(self::SECRET_FIELDS));
    }

    private static function str(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    private function host(): string
    {
        return self::HOSTS[$this->mode()];
    }

    private function salt(): string
    {
        return (string) $this->settings()->jazzcash_integrity_salt;
    }

    private function settings(): GatewaySettings
    {
        return app(GatewaySettings::class);
    }
}
