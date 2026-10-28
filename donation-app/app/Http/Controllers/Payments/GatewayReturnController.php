<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Payments\DTOs\CallbackPayload;
use App\Payments\GatewayRegistry;
use App\Payments\Gateways\Easypaisa\EasypaisaGateway;
use App\Services\PaymentCallbackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Where gateways send donors (and their results) back to us (FR-PAY-02).
 * CSRF is off for /payments/* (gateways post here); every request is stored and
 * verified by PaymentCallbackService before anything changes.
 */
class GatewayReturnController extends Controller
{
    public function __construct(private readonly PaymentCallbackService $callbacks) {}

    /** JazzCash hosted page posts the signed result here (browser). */
    public function jazzcashReturn(Request $request): RedirectResponse
    {
        $log = $this->callbacks->handle(CallbackPayload::fromRequest(PaymentMethod::JazzCash, $request));

        return $this->toResult($this->callbacks->paymentFor($log));
    }

    /** JazzCash server-to-server notification (IPN): same checks, plain answer. */
    public function jazzcashNotify(Request $request): Response
    {
        $this->callbacks->handle(CallbackPayload::fromRequest(PaymentMethod::JazzCash, $request));

        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    /**
     * Easypaisa step 1 comes back with auth_token: forward the donor to Confirm.jsf.
     * Anything else is treated as the final result.
     */
    public function easypaisaReturn(Request $request, GatewayRegistry $gateways): View|RedirectResponse
    {
        $token = $request->string('auth_token')->toString();

        if ($token !== '') {
            $gateway = $gateways->get(PaymentMethod::Easypaisa);
            assert($gateway instanceof EasypaisaGateway);

            return view('payments.redirect', [
                'checkout' => $gateway->confirmStep($token, route('payments.easypaisa.done')),
                'gateway' => 'Easypaisa',
            ]);
        }

        return $this->easypaisaDone($request);
    }

    /** Easypaisa final redirect: only a pointer — the result comes from the status inquiry. */
    public function easypaisaDone(Request $request): RedirectResponse
    {
        $log = $this->callbacks->handle(CallbackPayload::fromRequest(PaymentMethod::Easypaisa, $request));

        return $this->toResult($this->callbacks->paymentFor($log));
    }

    /** What the donor sees afterwards. Shows only the donation number, amount and status. */
    public function result(string $reference): View
    {
        $payment = Payment::query()->with('donation')->where('reference', $reference)->first();

        return view('payments.result', ['payment' => $payment]);
    }

    private function toResult(?Payment $payment): RedirectResponse
    {
        return redirect()->route('payments.result', ['reference' => $payment->reference ?? 'unknown']);
    }
}
