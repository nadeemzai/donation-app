<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\QurbaniAnimal;
use App\Models\QurbaniOffering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A tagged animal for Eid day 1, no shares yet.
 *
 * @extends Factory<QurbaniAnimal>
 */
class QurbaniAnimalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offering_id' => QurbaniOffering::factory(),
            'tag_no' => 'T-'.fake()->unique()->numberBetween(1, 99999),
            'slaughter_day' => 1,
            'location' => 'Main site',
        ];
    }
}
