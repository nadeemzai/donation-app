<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/** POST /donations/{number}/proof — the bank / Raast transfer screenshot. */
class ProofRequest extends FormRequest
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
            'reference' => ['required', 'string', 'max:100'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.(int) config('donation.donate.proof_max_kb')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reference.required' => __('Enter the transaction ID from your bank or app.'),
            'proof.required' => __('Attach a screenshot or photo of the transfer.'),
            'proof.mimes' => __('The proof must be a JPG, PNG, WebP or PDF file.'),
            'proof.max' => __('The proof file must be 5 MB or smaller.'),
        ];
    }

    /**
     * @return array<string, array{description: string, example: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'reference' => ['description' => 'Transaction ID from the bank or wallet app.', 'example' => 'RAAST-88120044'],
            'proof' => ['description' => 'Screenshot or photo (JPG, PNG, WebP or PDF, max 5 MB).', 'example' => null],
        ];
    }
}
