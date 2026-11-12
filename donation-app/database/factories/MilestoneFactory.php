<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Milestone>
 */
class MilestoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->randomElement(['Site survey', 'Boring done', 'Pump installed', 'Handover to community']),
            'due_on' => today()->addWeeks(2)->toDateString(),
        ];
    }
}
