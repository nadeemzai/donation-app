<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DonationChannel;
use App\Enums\PledgeFrequency;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\RecurringPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active monthly pledge due today. Tests that need other states use the service.
 *
 * @extends Factory<RecurringPlan>
 */
class RecurringPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = today();

        return [
            'donor_id' => Donor::factory(),
            'fund_id' => Fund::factory(),
            'amount' => fake()->randomElement(['1000.00', '2500.00', '5000.00']),
            'frequency' => PledgeFrequency::Monthly,
            'day_of_month' => min(28, $start->day),
            'starts_on' => $start->toDateString(),
            'channel' => DonationChannel::Admin,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (RecurringPlan $plan): void {
            $plan->next_due_on ??= $plan->starts_on;
        });
    }
}
