<?php

declare(strict_types=1);

namespace App\Http\Requests\Site;

use App\Enums\PaymentMethod;
use App\Models\Campaign;
use App\Models\Fund;
use App\Payments\GatewayRegistry;
use App\Support\Cnic;
use App\Support\Money;
use App\Support\PakistanPhone;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** The public donate form (Week 6 Day 2). Anyone may submit it; it is rate-limited by the route. */
class DonateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            // "5,000" and "5 000" are fine on a public form.
            'amount' => preg_replace('/[\s,]/', '', (string) $this->input('amount')),
            'email' => blank($this->input('email')) ? null : strtolower(trim((string) $this->input('email'))),
            'cnic' => blank($this->input('cnic')) ? null : trim((string) $this->input('cnic')),
            'is_anonymous' => $this->boolean('is_anonymous'),
            'whatsapp_opt_in' => $this->boolean('whatsapp_opt_in'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $min = (int) config('donation.donate.min');
        $max = (int) config('donation.donate.max');
        $methods = array_map(fn (PaymentMethod $m): string => $m->value, app(GatewayRegistry::class)->donorMethods());
        $bank = PaymentMethod::BankTransfer->value;

        return [
            'campaign_id' => ['nullable', 'integer'],
            'fund_id' => ['nullable', 'required_without:campaign_id', 'integer'],
            'amount' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/', 'numeric', "min:{$min}", "max:{$max}"],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! PakistanPhone::isValid($value)) {
                    $fail(__('Enter a Pakistani mobile number, e.g. 0300-1234567.'));
                }
            }],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'cnic' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! Cnic::isValid($value)) {
                    $fail(__('Enter the 13-digit CNIC, e.g. 35202-1234567-1, or leave it empty.'));
                }
            }],
            'payment_method' => ['required', Rule::in($methods)],
            'reference' => ["required_if:payment_method,{$bank}", 'nullable', 'string', 'max:100'],
            'proof' => ["required_if:payment_method,{$bank}", 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.(int) config('donation.donate.proof_max_kb')],
            'is_anonymous' => ['boolean'],
            'whatsapp_opt_in' => ['boolean'],
            'dedication' => ['nullable', 'string', 'max:150'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /**
     * The campaign must be live, the fund active.
     *
     * @return list<Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['campaign_id', 'fund_id'])) {
                    return;
                }

                $campaignId = $this->input('campaign_id');

                if (filled($campaignId)) {
                    if (Campaign::query()->live()->whereKey((int) $campaignId)->doesntExist()) {
                        $validator->errors()->add('campaign_id', __('This campaign is no longer taking donations. Please choose a fund.'));
                    }

                    return;
                }

                if (Fund::query()->active()->whereKey((int) $this->input('fund_id'))->doesntExist()) {
                    $validator->errors()->add('fund_id', __('Please choose what you would like to give to.'));
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
            'fund_id.required_without' => __('Please choose what you would like to give to.'),
            'amount.required' => __('Enter an amount.'),
            'amount.regex' => __('Enter the amount in rupees, e.g. 5000.'),
            'amount.min' => __('The smallest online donation is Rs :min.', ['min' => number_format((int) config('donation.donate.min'))]),
            'amount.max' => __('For donations above Rs :max please contact us.', ['max' => number_format((int) config('donation.donate.max'))]),
            'payment_method.required' => __('Choose how you would like to pay.'),
            'payment_method.in' => __('That payment method is not available right now. Please choose another.'),
            'reference.required_if' => __('Enter the transaction ID from your bank or app.'),
            'proof.required_if' => __('Attach a screenshot or photo of the transfer.'),
            'proof.mimes' => __('The proof must be a JPG, PNG, WebP or PDF file.'),
            'proof.max' => __('The proof file must be 5 MB or smaller.'),
            'idempotency_key.*' => __('Your form expired. Please check your details and submit again.'),
        ];
    }

    /** The visitor's amount as a money string, e.g. "5000.00". */
    public function amount(): string
    {
        return Money::normalise((string) $this->validated('amount'));
    }

    /** A validated text field, trimmed; null when empty. */
    public function optionalString(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::from((string) $this->validated('payment_method'));
    }
}
