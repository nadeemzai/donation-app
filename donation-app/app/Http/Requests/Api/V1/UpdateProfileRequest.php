<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Donor;
use App\Support\Cnic;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT /me — send only what changes. Name, phone and email can't change here (receipts go
 * to them); a CNIC can be added once.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Donor;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $donor = $this->user();
        $canAddCnic = $donor instanceof Donor && blank($donor->cnic);
        $donorId = $donor instanceof Donor ? $donor->id : 0;

        return [
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'language' => ['sometimes', 'in:en,ur'],
            'whatsapp_opt_in' => ['sometimes', 'boolean'],
            'email_opt_in' => ['sometimes', 'boolean'],
            'cnic' => $canAddCnic
                ? ['sometimes', 'nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail) use ($donorId): void {
                    if (blank($value)) {
                        return;
                    }

                    if (! is_string($value) || ! Cnic::isValid($value)) {
                        $fail(__('Enter the 13-digit CNIC, e.g. 35202-1234567-1.'));

                        return;
                    }

                    if (Donor::withTrashed()->where('cnic_hash', Cnic::hash($value))->whereKeyNot($donorId)->exists()) {
                        $fail(__('This CNIC is already on another record. Please contact us and we will sort it out.'));
                    }
                }]
                : ['prohibited'],
            'name' => ['prohibited'],
            'phone' => ['prohibited'],
            'email' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cnic.prohibited' => __('Your CNIC is already on record. Please contact us to change it.'),
            'name.prohibited' => __('Please contact us to change your name.'),
            'phone.prohibited' => __('Please contact us to change your mobile number.'),
            'email.prohibited' => __('Please contact us to change your email.'),
        ];
    }

    /**
     * @return array<string, array{description: string, example: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'city' => ['description' => 'City.', 'example' => 'Lahore'],
            'language' => ['description' => 'en or ur.', 'example' => 'ur'],
            'whatsapp_opt_in' => ['description' => 'Receipts and updates on WhatsApp.', 'example' => true],
            'email_opt_in' => ['description' => 'Receipts and updates by email.', 'example' => true],
            'cnic' => ['description' => 'Add once, for tax certificates.', 'example' => '35202-1234567-1'],
        ];
    }
}
