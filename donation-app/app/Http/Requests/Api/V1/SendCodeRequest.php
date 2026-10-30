<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Services\Site\DonorLoginService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** POST /auth/login and /auth/otp/send — start (or repeat) a one-time-code login. */
class SendCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
        ];
    }

    /**
     * @return array<string, array{description: string, example: string}>
     */
    public function bodyParameters(): array
    {
        return [
            'identifier' => ['description' => 'The donor\'s Pakistani mobile number or email address.', 'example' => '0300-1234567'],
        ];
    }

    public function identifier(): string
    {
        return (string) DonorLoginService::normalise((string) $this->validated('identifier'));
    }
}
