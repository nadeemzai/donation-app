<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Services\Site\DonorLoginService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** POST /auth/otp/verify — swap a one-time code for an API token. */
class VerifyCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => preg_replace('/\D/', '', (string) $this->input('code'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || DonorLoginService::normalise($value) === null) {
                    $fail(__('Enter your mobile number (e.g. 0300-1234567) or your email address.'));
                }
            }],
            'code' => ['required', 'digits:6'],
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, array{description: string, example: string}>
     */
    public function bodyParameters(): array
    {
        return [
            'identifier' => ['description' => 'The same mobile number or email the code was sent to.', 'example' => '0300-1234567'],
            'code' => ['description' => 'The 6-digit code from WhatsApp or email.', 'example' => '482913'],
            'device_name' => ['description' => 'Shown to the donor in their list of logged-in devices.', 'example' => 'Ayesha\'s Galaxy A54'],
        ];
    }

    public function identifier(): string
    {
        return (string) DonorLoginService::normalise((string) $this->validated('identifier'));
    }
}
