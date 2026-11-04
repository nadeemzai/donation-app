<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Donor;
use App\Support\Phone;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** POST /qurbani/bookings (Week 11). With a donor token the donor is known; guests give contact details. */
class QurbaniBookingRequest extends FormRequest
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
        $guest = $this->donor() === null;

        return [
            'offering_id' => ['required', 'integer'],
            'names' => ['required', 'array', 'min:1', 'max:21'],
            'names.*' => ['required', 'string', 'min:2', 'max:120'],
            'name' => [$guest ? 'required' : 'nullable', 'string', 'min:2', 'max:120'],
            'phone' => ['nullable', $guest ? 'required_without:email' : 'nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (filled($value) && (! is_string($value) || ! Phone::isValid($value))) {
                    $fail(__('Enter a mobile number: :example.', ['example' => Phone::EXAMPLE]));
                }
            }],
            'email' => ['nullable', $guest ? 'required_without:phone' : 'nullable', 'email:rfc', 'max:255'],
            'whatsapp_opt_in' => ['boolean'],
        ];
    }

    /**
     * @return array<string, array{description: string, example: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'offering_id' => ['description' => 'offerings[].id from GET /qurbani.', 'example' => 3],
            'names' => ['description' => 'One name per share — on whose behalf the Qurbani is offered.', 'example' => ['Ayesha Khan', 'Late Muhammad Aslam']],
            'name' => ['description' => 'Without a donor token: your name.', 'example' => 'Ayesha Khan'],
            'phone' => ['description' => 'Without a donor token: a phone or an email. Abroad: + and country code.', 'example' => '+44 7700 900123'],
            'email' => ['description' => 'Without a donor token: required when there is no phone.', 'example' => 'ayesha@example.com'],
            'whatsapp_opt_in' => ['description' => 'New donors: updates on WhatsApp.', 'example' => true],
        ];
    }

    public function donor(): ?Donor
    {
        $donor = $this->user('sanctum');

        return $donor instanceof Donor ? $donor : null;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_values(array_map(fn (mixed $n): string => (string) $n, (array) $this->validated('names')));
    }

    public function optionalString(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
