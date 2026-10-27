<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Fund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * Default: a draft campaign with a goal, running for 30 days.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fund_id' => Fund::factory(),
            'title' => fake()->randomElement(['Winter Relief', 'Clean Water Wells', 'School Fees Support', 'Ration Drive', 'Medical Aid']).' '.fake()->unique()->numberBetween(1, 9999),
            'summary' => fake()->sentence(12),
            'story' => fake()->paragraphs(3, true),
            'goal_amount' => fake()->randomElement(['250000.00', '500000.00', '1000000.00']),
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDays(30)->toDateString(),
            'is_featured' => false,
        ];
    }

    public function live(): static
    {
        return $this->afterMaking(function (Campaign $campaign): void {
            $campaign->status = CampaignStatus::Live;
            $campaign->published_at = now();
        });
    }

    public function closed(): static
    {
        return $this->afterMaking(function (Campaign $campaign): void {
            $campaign->status = CampaignStatus::Closed;
            $campaign->published_at = now()->subDays(40);
            $campaign->closed_at = now();
        });
    }

    /** A general cause with no goal and no end date. */
    public function openEnded(): static
    {
        return $this->state(fn (array $attributes) => ['goal_amount' => null, 'ends_at' => null]);
    }
}
