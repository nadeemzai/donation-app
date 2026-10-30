<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Support\PakistanPhone;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** POST /auth/register — a new donor signs up in the app; a code then proves the number. */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => blank($this->input('email')) ? null : mb_strtolower(trim((string) $this->input('email'))),
            'whatsapp_opt_in' => $this->boolean('whatsapp_opt_in'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! PakistanPhone::isValid($value)) {
                    $fail(__('Enter a Pakistani mobile number, e.g. 0300-1234567.'));
                }
            }],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'in:en,ur'],
            'whatsapp_opt_in' => ['boolean'],
        ];
    }

    /**
     * @return array<string, array{description: string, example: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Full name.', 'example' => 'Ayesha Khan'],
            'phone' => ['description' => 'Pakistani mobile number. The login code goes here.', 'example' => '0300-1234567'],
            'email' => ['description' => 'Optional. Receipts are also emailed here.', 'example' => 'ayesha@example.com'],
            'city' => ['description' => 'Optional.', 'example' => 'Lahore'],
            'language' => ['description' => 'en or ur — language for receipts and messages.', 'example' => 'en'],
            'whatsapp_opt_in' => ['description' => 'Receipts and updates on WhatsApp.', 'example' => true],
        ];
    }

    public function optionalString(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
