<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\PaymentMethod;
use App\Models\Campaign;
use App\Models\Donor;
use App\Models\Fund;
use App\Payments\GatewayRegistry;
use App\Support\Cnic;
use App\Support\Money;
use App\Support\PakistanPhone;
use Closure;
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
        $guest = $this->donor() === null;
        $methods = array_map(fn (PaymentMethod $m): string => $m->value, app(GatewayRegistry::class)->donorMethods());
        $min = (int) config('donation.donate.min');
        $max = (int) config('donation.donate.max');

        return [
            'fund_code' => ['nullable', 'required_without:campaign_slug', 'string', 'max:30'],
            'campaign_slug' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/', 'numeric', "min:{$min}", "max:{$max}"],
            'payment_method' => ['required', Rule::in($methods)],
            'wallet_number' => ['nullable', 'required_with:cnic_last6', 'regex:/^03\d{9}$/'],
            'cnic_last6' => ['nullable', 'required_with:wallet_number', 'digits:6'],
            'name' => [$guest ? 'required' : 'nullable', 'string', 'min:2', 'max:120'],
            'phone' => [$guest ? 'required' : 'nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (filled($value) && (! is_string($value) || ! PakistanPhone::isValid($value))) {
                    $fail(__('Enter a Pakistani mobile number, e.g. 0300-1234567.'));
                }
            }],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'cnic' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (filled($value) && (! is_string($value) || ! Cnic::isValid($value))) {
                    $fail(__('Enter the 13-digit CNIC, e.g. 35202-1234567-1, or leave it empty.'));
                }
            }],
            'whatsapp_opt_in' => ['boolean'],
            'is_anonymous' => ['boolean'],
            'dedication' => ['nullable', 'string', 'max:150'],
            'idempotency_key' => ['required', 'uuid'],
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

                if (filled($this->input('campaign_slug'))) {
                    $campaign = $this->campaign();

                    if ($campaign === null || $campaign->hasEnded()) {
                        $validator->errors()->add('campaign_slug', __('This campaign is no longer taking donations. Please choose a fund.'));
                    }
                } elseif ($this->fund() === null) {
                    $validator->errors()->add('fund_code', __('Please choose what you would like to give to.'));
                }

                $method = PaymentMethod::tryFrom((string) $this->input('payment_method'));

                if (filled($this->input('wallet_number')) && $method !== null && ! $method->isGateway()) {
                    $validator->errors()->add('wallet_number', __('A wallet number is only used for JazzCash or Easypaisa.'));
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
            'phone' => ['description' => 'Required without a donor token.', 'example' => '0300-1234567'],
            'email' => ['description' => 'Optional.', 'example' => 'ayesha@example.com'],
            'cnic' => ['description' => 'Optional, for tax certificates (new donors only).', 'example' => '35202-1234567-1'],
            'whatsapp_opt_in' => ['description' => 'New donors: receipts on WhatsApp.', 'example' => true],
            'is_anonymous' => ['description' => 'Hide the donor\'s name in public lists.', 'example' => false],
            'dedication' => ['description' => 'On behalf of / in memory of.', 'example' => 'My late father'],
            'idempotency_key' => ['description' => 'A UUID; or send it as the Idempotency-Key header. Repeating it returns the same donation.', 'example' => '3f2b9f7c-8d1e-4a55-9c3e-1d2f3a4b5c6d'],
        ];
    }

    public function donor(): ?Donor
    {
        $donor = $this->user('sanctum');

        return $donor instanceof Donor ? $donor : null;
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
