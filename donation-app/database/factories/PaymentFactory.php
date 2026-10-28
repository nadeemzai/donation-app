<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Donation;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A just-started JazzCash payment for a pending JazzCash donation.
 * For real status changes use PaymentService.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'donation_id' => Donation::factory()->viaJazzCash(),
            'method' => PaymentMethod::JazzCash,
            'amount' => fn (array $attributes): string => (string) Donation::query()->whereKey($attributes['donation_id'])->value('amount'),
            'currency' => 'PKR',
            'mode' => 'sandbox',
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
