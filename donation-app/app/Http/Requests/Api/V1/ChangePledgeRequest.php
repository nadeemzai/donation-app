<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Services\DonorPledgeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** PATCH /me/recurring-plans/{id} — pause, resume, cancel and/or a new amount. */
class ChangePledgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (filled($this->input('amount'))) {
            $this->merge(['amount' => preg_replace('/[\s,]/', '', (string) $this->input('amount'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['nullable', 'required_without:amount', Rule::in(DonorPledgeService::ACTIONS)],
            'amount' => ['nullable', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, array{description: string, example: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'action' => ['description' => 'pause, resume or cancel. Leave out to only change the amount.', 'example' => 'pause'],
            'amount' => ['description' => 'New amount for future due dates.', 'example' => '7500'],
            'reason' => ['description' => 'Optional, when cancelling.', 'example' => 'Moving abroad'],
        ];
    }
}
