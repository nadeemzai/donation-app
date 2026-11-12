<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\PaymentMethod;
use App\Models\Campaign;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\QurbaniBooking;
use App\Models\RecurringPlanPeriod;
use App\Payments\GatewayRegistry;
use App\Services\QurbaniService;
use App\Support\Cnic;
use App\Support\Money;
use App\Support\PakistanPhone;
use App\Support\Phone;
use App\Support\QurbaniToken;
use Closure;
use DomainException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * POST /donations (Week 7 Day 4). With a donor token the donor's own details are used;
 * without one, name and mobile number are required (like the website).
 */
class StartDonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => preg_replace('/[\s,]/', '', (string) $this->input('amount')),
            'email' => blank($this->input('email')) ? null : mb_strtolower(trim((string) $this->input('email'))),
            'wallet_number' => blank($this->input('wallet_number')) ? null : PakistanPhone::normalize((string) $this->input('wallet_number')),
            'is_anonymous' => $this->boolean('is_anonymous'),
            'whatsapp_opt_in' => $this->boolean('whatsapp_opt_in'),
            // The key may come as a header (preferred) or in the body.
            'idempotency_key' => $this->header('Idempotency-Key') ?? $this->input('idempotency_key'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $booking = $this->qurbaniBooking() !== null;
        $guest = $this->donor() === null && ! $booking;
        $methods = array_map(fn (PaymentMethod $m): string => $m->value, app(GatewayRegistry::class)->donorMethods());
        $min = (int) config('donation.donate.min');
        $max = (int) config('donation.donate.max');

        return [
            'fund_code' => ['nullable', $booking ? 'nullable' : 'required_without:campaign_slug', 'string', 'max:30'],
            'campaign_slug' => ['nullable', 'string', 'max:120'],
            // A Qurbani booking's amount is its total, set by the server.
            'amount' => $booking ? ['nullable'] : ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/', 'numeric', "min:{$min}", "max:{$max}"],
            'payment_method' => ['required', Rule::in($methods)],
            'wallet_number' => ['nullable', 'required_with:cnic_last6', 'regex:/^03\d{9}$/'],
            'cnic_last6' => ['nullable', 'required_with:wallet_number', 'digits:6'],
            'name' => [$guest ? 'required' : 'nullable', 'string', 'min:2', 'max:120'],
            // Guests: a phone (Pakistan or abroad) or an email.
            'phone' => ['nullable', $guest ? 'required_without:email' : 'nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (filled($value) && (! is_string($value) || ! Phone::isValid($value))) {
                    $fail(__('Enter a mobile number: :example.', ['example' => Phone::EXAMPLE]));
                }
            }],
            'email' => ['nullable', $guest ? 'required_without:phone' : 'nullable', 'email:rfc', 'max:255'],
            'cnic' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (filled($value) && (! is_string($value) || ! Cnic::isValid($value))) {
                    $fail(__('Enter the 13-digit CNIC, e.g. 35202-1234567-1, or leave it empty.'));
                }
            }],
            'whatsapp_opt_in' => ['boolean'],
            'is_anonymous' => ['boolean'],
            'dedication' => ['nullable', 'string', 'max:150'],
            'idempotency_key' => ['required', 'uuid'],
            // Paying a pledge due date (from /me/recurring-plans → pay_now). Token only.
            'pledge_period_id' => ['nullable', 'integer'],
            // Paying a Qurbani booking (from POST /qurbani/bookings). Week 11.
            'qurbani_booking_token' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return list<Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['fund_code', 'campaign_slug', 'payment_method'])) {
                    return;
                }

                if (filled($this->input('qurbani_booking_token'))) {
                    $booking = $this->qurbaniBooking();

                    if ($booking === null) {
                        $validator->errors()->add('qurbani_booking_token', __('That Qurbani booking is not valid. Please book again.'));
                    } else {
                        try {
                            app(QurbaniService::class)->assertPayable($booking);
                        } catch (DomainException $e) {
                            $validator->errors()->add('qurbani_booking_token', $e->getMessage());
                        }
                    }
                } elseif (filled($this->input('campaign_slug'))) {
                    $campaign = $this->campaign();

                    if ($campaign === null || $campaign->hasEnded()) {
                        $validator->errors()->add('campaign_slug', __('This campaign is no longer taking donations. Please choose a fund.'));
                    }
                } elseif ($this->fund() === null) {
                    $validator->errors()->add('fund_code', __('Please choose what you would like to give to.'));
                }

                $method = PaymentMethod::tryFrom((string) $this->input('payment_method'));

                if (filled($this->input('pledge_period_id')) && $this->pledgePeriod() === null) {
                    $validator->errors()->add('pledge_period_id', __('This pledge payment is not open — it may already be paid.'));
                }

                if (filled($this->input('wallet_number')) && $method !== null && ! $method->isGateway()) {
                    $validator->errors()->add('wallet_number', __('A wallet number is only used for JazzCash or Easypaisa.'));
                }

                // JazzCash / Easypaisa need a Pakistani mobile: the wallet number, or the donor's own.
                $donor = $this->qurbaniBooking()->donor ?? $this->donor();
                $phone = $donor !== null ? (string) $donor->phone : (string) $this->input('phone');

                if ($method !== null && $method->isGateway() && blank($this->input('wallet_number'))
                    && ! $validator->errors()->has('phone') && ! Phone::isPakistaniMobile($phone)) {
                    $validator->errors()->add('payment_method', __(':method needs a Pakistani mobile number. From abroad, please pay by bank transfer.', ['method' => $method->label()]));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fund_code.required_without' => __('Please choose what you would like to give to.'),
            'amount.min' => __('The smallest online donation is Rs :min.', ['min' => number_format((int) config('donation.donate.min'))]),
            'amount.max' => __('For donations above Rs :max please contact us.', ['max' => number_format((int) config('donation.donate.max'))]),
            'payment_method.in' => __('That payment method is not available right now. Please choose another.'),
            'wallet_number.regex' => __('Enter the wallet number as 03XXXXXXXXX (11 digits).'),
            'idempotency_key.*' => __('Send a new UUID as Idempotency-Key with each new donation.'),
        ];
    }

    /**
     * @return array<string, array{description: string, example: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'fund_code' => ['description' => 'Give to this fund. Either this or campaign_slug.', 'example' => 'ZAKAT'],
            'campaign_slug' => ['description' => 'Give to this live campaign (its fund is used).', 'example' => 'winter-relief-2026'],
            'amount' => ['description' => 'Rupees; commas allowed; up to 2 decimals.', 'example' => '5000'],
            'payment_method' => ['description' => 'One of the methods in /app/config.', 'example' => 'jazzcash'],
            'wallet_number' => ['description' => 'Charge this JazzCash/Easypaisa wallet directly (with cnic_last6). Leave out to get the hosted payment page instead.', 'example' => '03001234567'],
            'cnic_last6' => ['description' => 'Last 6 digits of the wallet owner\'s CNIC. Passed to the gateway, never stored.', 'example' => '345671'],
            'name' => ['description' => 'Required without a donor token.', 'example' => 'Ayesha Khan'],
            'phone' => ['description' => 'Without a donor token: a phone or an email. Pakistani (0300…) or abroad with country code (+44…). JazzCash/Easypaisa need a Pakistani number (or wallet_number).', 'example' => '0300-1234567'],
            'email' => ['description' => 'Without a donor token: required when there is no phone.', 'example' => 'ayesha@example.com'],
            'cnic' => ['description' => 'Optional, for tax certificates (new donors only).', 'example' => '35202-1234567-1'],
            'whatsapp_opt_in' => ['description' => 'New donors: receipts on WhatsApp.', 'example' => true],
            'is_anonymous' => ['description' => 'Hide the donor\'s name in public lists.', 'example' => false],
            'dedication' => ['description' => 'On behalf of / in memory of.', 'example' => 'My late father'],
            'idempotency_key' => ['description' => 'A UUID; or send it as the Idempotency-Key header. Repeating it returns the same donation.', 'example' => '3f2b9f7c-8d1e-4a55-9c3e-1d2f3a4b5c6d'],
            'pledge_period_id' => ['description' => 'With a donor token: pay this pledge due date (pay_now.pledge_period_id from /me/recurring-plans).', 'example' => 31],
            'qurbani_booking_token' => ['description' => 'Pay a Qurbani booking (booking_token from POST /qurbani/bookings). Fund, amount and donor come from the booking.', 'example' => 'eyJpdiI6…'],
        ];
    }

    public function donor(): ?Donor
    {
        $donor = $this->user('sanctum');

        return $donor instanceof Donor ? $donor : null;
    }

    /** The Qurbani booking this donation pays (Week 11), from its token. */
    public function qurbaniBooking(): ?QurbaniBooking
    {
        $token = $this->input('qurbani_booking_token');

        return is_string($token) && $token !== '' ? QurbaniToken::booking($token) : null;
    }

    /** The donor's own unpaid pledge due date this donation pays, if given and valid. */
    public function pledgePeriod(): ?RecurringPlanPeriod
    {
        $donor = $this->donor();
        $id = (int) $this->input('pledge_period_id');

        if ($donor === null || $id <= 0) {
            return null;
        }

        $period = RecurringPlanPeriod::query()->with('plan')->find($id);

        return $period !== null && $period->isPayable() && $period->plan->donor_id === $donor->id ? $period : null;
    }

    public function campaign(): ?Campaign
    {
        $slug = (string) $this->input('campaign_slug');

        return $slug === '' ? null : Campaign::query()->live()->where('slug', $slug)->first();
    }

    public function fund(): ?Fund
    {
        $code = strtoupper((string) $this->input('fund_code'));

        return $code === '' ? null : Fund::query()->active()->where('code', $code)->first();
    }

    public function amount(): string
    {
        return Money::normalise((string) $this->validated('amount'));
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::from((string) $this->validated('payment_method'));
    }

    public function optionalString(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
