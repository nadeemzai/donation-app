<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\PaymentMethod;
use App\Enums\PledgeFrequency;
use App\Payments\GatewayRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** POST /me/recurring-plans — the donor sets up a pledge. */
class StartPledgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['amount' => preg_replace('/[\s,]/', '', (string) $this->input('amount'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $methods = array_map(fn (PaymentMethod $m): string => $m->value, app(GatewayRegistry::class)->donorMethods());

        return [
            'fund_code' => ['nullable', 'required_without:campaign_slug', 'string', 'max:30'],
            'campaign_slug' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'frequency' => ['required', Rule::enum(PledgeFrequency::class)],
            'starts_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'payment_method' => ['nullable', Rule::in($methods)],
        ];
    }

    /**
     * @return array<string, array{description: string, example: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'fund_code' => ['description' => 'Pledge to this fund. Either this or campaign_slug.', 'example' => 'ZAKAT'],
            'campaign_slug' => ['description' => 'Pledge to this live campaign.', 'example' => 'winter-relief-2026'],
            'amount' => ['description' => 'Rupees each time.', 'example' => '5000'],
            'frequency' => ['description' => 'monthly, quarterly or yearly.', 'example' => 'monthly'],
            'starts_on' => ['description' => 'First due date (YYYY-MM-DD), today or later. Default today.', 'example' => '2026-12-15'],
            'payment_method' => ['description' => 'How the donor usually pays (a method from /app/config).', 'example' => 'jazzcash'],
        ];
    }
}
