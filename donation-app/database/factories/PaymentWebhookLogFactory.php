<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\PaymentWebhookLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentWebhookLog>
 */
class PaymentWebhookLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fields = ['pp_TxnRefNo' => 'T'.fake()->numerify('############').'ABCDE', 'pp_ResponseCode' => '000'];

        return [
            'method' => PaymentMethod::JazzCash,
            'http_method' => 'POST',
            'url' => 'https://example.test/payments/jazzcash/callback',
            'ip' => fake()->ipv4(),
            'headers' => ['content-type' => ['application/x-www-form-urlencoded']],
            'payload' => http_build_query($fields),
            'fields' => $fields,
        ];
    }
}
