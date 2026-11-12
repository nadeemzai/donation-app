<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ImpactUpdate;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A published update on a public project.
 *
 * @extends Factory<ImpactUpdate>
 */
class ImpactUpdateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory()->public(),
            'title' => 'Pump installed — 40 families have clean water',
            'body' => '<p>The hand pump was installed this week and tested by the village committee.</p>',
            'published_at' => now()->subMinute(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['published_at' => null]);
    }
}
