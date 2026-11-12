<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Fund;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active project with a Rs 500,000 budget, paid from a (non-Zakat) fund holding Rs 10,000,000.
 *
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Water pump '.fake()->unique()->city(),
            'summary' => 'A hand pump and storage tank for clean drinking water.',
            'fund_id' => Fund::factory()->funded(),
            'budget' => '500000.00',
            'status' => ProjectStatus::Active,
            'starts_on' => today()->toDateString(),
            'location' => fake()->city(),
        ];
    }

    public function public(): static
    {
        return $this->state(['is_public' => true]);
    }

    public function planned(): static
    {
        return $this->state(['status' => ProjectStatus::Planned]);
    }
}
