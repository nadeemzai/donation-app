<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignUpdate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignUpdate>
 */
class CampaignUpdateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'title' => fake()->sentence(5),
            'body' => fake()->paragraphs(2, true),
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['published_at' => null]);
    }
}
