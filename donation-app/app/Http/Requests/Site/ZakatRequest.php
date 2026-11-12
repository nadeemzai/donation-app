<?php

declare(strict_types=1);

namespace App\Http\Requests\Site;

use App\Support\Zakat\ZakatCalculator;
use Illuminate\Foundation\Http\FormRequest;

/** The Zakat calculator form (Week 6 Day 3). Figures are never stored or logged. */
class ZakatRequest extends FormRequest
{
    public const WEIGHT_FIELDS = ['gold_tola', 'silver_tola'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return list<string> */
    public static function fields(): array
    {
        return [...self::WEIGHT_FIELDS, ...ZakatCalculator::MONEY_FIELDS, 'debts'];
    }

    protected function prepareForValidation(): void
    {
        $clean = [];

        foreach (self::fields() as $field) {
            $value = preg_replace('/[\s,]/', '', (string) $this->input($field));
            $clean[$field] = $value === '' ? null : $value;
        }

        $this->merge($clean);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (self::WEIGHT_FIELDS as $field) {
            $rules[$field] = ['nullable', 'regex:/^\d{1,5}(\.\d{1,3})?$/'];
        }

        foreach ([...ZakatCalculator::MONEY_FIELDS, 'debts'] as $field) {
            $rules[$field] = ['nullable', 'regex:/^\d{1,12}(\.\d{1,2})?$/'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gold_tola.regex' => __('Enter the gold weight in tola, e.g. 2.5'),
            'silver_tola.regex' => __('Enter the silver weight in tola, e.g. 10'),
            '*.regex' => __('Enter an amount in rupees, e.g. 150000'),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function figures(): array
    {
        $figures = [];

        foreach (self::fields() as $field) {
            $value = $this->validated($field);
            $figures[$field] = is_string($value) ? $value : null;
        }

        return $figures;
    }
}
