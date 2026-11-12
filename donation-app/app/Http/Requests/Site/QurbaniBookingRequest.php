<?php

declare(strict_types=1);

namespace App\Http\Requests\Site;

use App\Support\Phone;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** The public Qurbani booking form (Week 11). Shares and capacity are checked by QurbaniService. */
class QurbaniBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $names = $this->input('names');

        $this->merge([
            'names' => is_array($names) ? array_values(array_filter(array_map(fn (mixed $n): string => trim(is_string($n) ? $n : ''), $names), fn (string $n): bool => $n !== '')) : [],
            'whatsapp_opt_in' => $this->boolean('whatsapp_opt_in'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'offering_id' => ['required', 'integer'],
            'names' => ['required', 'array', 'min:1', 'max:21'],
            'names.*' => ['string', 'min:2', 'max:120'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (filled($value) && (! is_string($value) || ! Phone::isValid($value))) {
                    $fail(__('Enter a mobile number: :example.', ['example' => Phone::EXAMPLE]));
                }
            }],
            'email' => ['nullable', 'required_without:phone', 'email:rfc', 'max:255'],
            'whatsapp_opt_in' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'offering_id.required' => __('Choose the animal.'),
            'names.required' => __('Enter a name for each share.'),
            'names.min' => __('Enter a name for each share.'),
            'names.max' => __('One booking can have up to 21 shares.'),
            'names.*.min' => __('Each name needs at least 2 letters.'),
            'name.required' => __('Please enter your name.'),
            'phone.required_without' => __('Give a mobile number or an email address so we can send your receipt.'),
            'email.required_without' => __('Give an email address or a mobile number so we can send your receipt.'),
        ];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        /** @var list<string> $names */
        $names = $this->validated('names');

        return $names;
    }

    public function optionalString(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
