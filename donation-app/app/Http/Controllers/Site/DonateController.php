<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Enums\CheckoutType;
use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\DonateRequest;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Fund;
use App\Payments\Exceptions\GatewayNotAvailable;
use App\Payments\GatewayRegistry;
use App\Services\DonationService;
use App\Services\PaymentService;
use App\Services\Site\WebDonorService;
use App\Settings\GatewaySettings;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * The public donate wizard (Week 6 Day 2, FR-DON-01, FR-PAY-01/03).
 *
 * One form, shown as four steps by Alpine: cause → amount → details → payment.
 * The form posts once; DonationService and PaymentService do all the work, so the
 * rules are the same as in the admin and the API.
 *
 *  - JazzCash / Easypaisa → payment started, donor sent to the gateway's page.
 *  - Bank transfer / Raast → pending donation with the proof file, checked by
 *    Finance in the proof queue; donor sees a thank-you page.
 *
 * A hidden idempotency key makes a double-click or a browser resubmit return the
 * same donation instead of creating two.
 */
class DonateController extends Controller
{
    public function __construct(
        private readonly DonationService $donations,
        private readonly PaymentService $payments,
        private readonly WebDonorService $donors,
        private readonly GatewayRegistry $gateways,
    ) {}

    public function create(Request $request): View
    {
        $campaign = null;
        $notice = null;
        $slug = $request->string('campaign')->toString();

        if ($slug !== '') {
            $campaign = Campaign::query()->live()->where('slug', $slug)->with(['fund.media', 'media'])->first();

            if ($campaign === null || $campaign->hasEnded()) {
                $notice = __('That campaign is no longer taking donations. You can still give to one of our funds below.');
                $campaign = null;
            }
        }

        $funds = Fund::query()->active()->with('media')->get();
        $code = strtoupper($request->string('fund')->toString());
        $selectedFund = $campaign !== null ? $campaign->fund_id : $funds->firstWhere('code', $code)?->id;

        return view('site.donate.create', [
            'campaign' => $campaign,
            'funds' => $funds,
            'selectedFund' => old('fund_id', $selectedFund !== null ? (string) $selectedFund : null),
            'methods' => $this->gateways->donorMethods(),
            'bank' => app(GatewaySettings::class),
            'presets' => config('donation.donate.presets'),
            'minAmount' => (int) config('donation.donate.min'),
            'idempotencyKey' => old('idempotency_key') ?: (string) Str::uuid(),
            'notice' => $notice,
        ]);
    }

    public function store(DonateRequest $request): View|RedirectResponse
    {
        $method = $request->paymentMethod();
        $campaignId = $request->filled('campaign_id') ? (int) $request->validated('campaign_id') : null;

        try {
            ['donor' => $donor, 'notes' => $notes] = $this->donors->resolve([
                'name' => (string) $request->validated('name'),
                'phone' => (string) $request->validated('phone'),
                'email' => $request->optionalString('email'),
                'cnic' => $request->optionalString('cnic'),
                'whatsapp_opt_in' => (bool) $request->validated('whatsapp_opt_in'),
            ], app()->getLocale());

            $donation = $this->donations->create([
                'donor_id' => $donor->id,
                'campaign_id' => $campaignId,
                'fund_id' => $campaignId === null ? (int) $request->validated('fund_id') : null,
                'amount' => $request->amount(),
                'channel' => DonationChannel::Web,
                'payment_method' => $method,
                'reference' => $method === PaymentMethod::BankTransfer ? trim((string) $request->validated('reference')) : null,
                'is_anonymous' => (bool) $request->validated('is_anonymous'),
                'dedication' => $request->optionalString('dedication'),
                'staff_note' => $notes !== [] ? implode("\n", $notes) : null,
                'idempotency_key' => (string) $request->validated('idempotency_key'),
            ]);
        } catch (DomainException $e) {
            return $this->backWithError($request, $e->getMessage());
        }

        if ($method === PaymentMethod::BankTransfer) {
            return $this->bankTransfer($request, $donation);
        }

        return $this->startGatewayPayment($request, $donation, $method);
    }

    /** Shown after a bank transfer (and when a gateway donation was already paid). Signed link, 7 days. */
    public function thanks(string $number): View
    {
        $donation = Donation::query()->where('donation_no', $number)->with(['fund.media', 'campaign'])->firstOrFail();

        return view('site.donate.thanks', ['donation' => $donation, 'bank' => app(GatewaySettings::class)]);
    }

    private function bankTransfer(DonateRequest $request, Donation $donation): RedirectResponse
    {
        // A resubmit of the same form must not attach a second file.
        $proof = $request->file('proof');

        if ($proof !== null && ! $donation->hasMedia(Donation::PROOF)) {
            $donation->addMedia($proof)->toMediaCollection(Donation::PROOF);
        }

        return redirect()->to(self::thanksUrl($donation));
    }

    private function startGatewayPayment(DonateRequest $request, Donation $donation, PaymentMethod $method): View|RedirectResponse
    {
        if ($donation->status->isSettled()) {
            return redirect()->to(self::thanksUrl($donation)); // a resubmit after paying
        }

        if ($donation->status !== DonationStatus::Pending) {
            return $this->backWithError($request, __('This donation was closed. Please submit the form again to start a new one.'));
        }

        try {
            [$payment, $instruction] = $this->payments->initiate($donation, $this->returnUrl($method));
        } catch (GatewayNotAvailable|DomainException $e) {
            $this->cancelUnlessPaying($donation, 'Payment could not start: '.$e->getMessage());

            return $this->backWithError($request, $e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->cancelUnlessPaying($donation, 'Payment could not start: gateway error');

            return $this->backWithError($request, __('We could not reach :gateway just now. No money was taken. Please try again, or choose another way to pay.', ['gateway' => $method->label()]));
        }

        return match ($instruction->type) {
            CheckoutType::FormPost => view('payments.redirect', ['checkout' => $instruction, 'gateway' => $method->label()]),
            CheckoutType::Redirect => redirect()->away((string) $instruction->url),
            CheckoutType::AwaitingDonor => redirect()->route('payments.result', ['reference' => $payment->reference]),
        };
    }

    private function returnUrl(PaymentMethod $method): string
    {
        return match ($method) {
            PaymentMethod::Easypaisa => route('payments.easypaisa.return'),
            default => route('payments.jazzcash.return'),
        };
    }

    /** Close a donation whose payment never started — unless another attempt is still open. */
    private function cancelUnlessPaying(Donation $donation, string $reason): void
    {
        $open = $donation->payments()
            ->whereIn('status', [PaymentStatus::Initiated->value, PaymentStatus::Pending->value])
            ->exists();

        if (! $open) {
            $this->donations->cancel($donation, $reason);
        }
    }

    private function backWithError(DonateRequest $request, string $message): RedirectResponse
    {
        // A new idempotency key is issued, so the next submit starts a fresh donation.
        return back()
            ->withInput($request->except(['proof', 'idempotency_key']))
            ->withErrors(['form' => $message]);
    }

    public static function thanksUrl(Donation $donation): string
    {
        return URL::temporarySignedRoute('donate.thanks', now()->addDays(7), ['number' => $donation->donation_no]);
    }
}
