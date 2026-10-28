<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DonationChannel;
use App\Enums\DonationStatus;
use App\Enums\PaymentMethod;
use App\Models\Donation;
use App\Models\Donor;
use App\Models\Fund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default: a pending cash donation recorded by staff.
 *
 * Note: paid() only sets the status for tests that need a settled donation.
 * It does NOT post to the fund ledger — use DonationService for real money flow.
 *
 * @extends Factory<Donation>
 */
class DonationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'donor_id' => Donor::factory(),
            'fund_id' => Fund::factory(),
            'amount' => fake()->randomElement(['500.00', '1000.00', '2500.00', '5000.00', '10000.00']),
            'channel' => DonationChannel::Admin,
            'payment_method' => PaymentMethod::Cash,
            'received_at' => now(),
        ];
    }

    public function paid(): static
    {
        return $this->afterMaking(function (Donation $donation): void {
            $donation->status = DonationStatus::Paid;
            $donation->paid_at = now();
        });
    }

    public function viaJazzCash(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => DonationChannel::Web,
            'payment_method' => PaymentMethod::JazzCash,
            'received_at' => null,
        ]);
    }

    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => ['is_anonymous' => true]);
    }
}
