<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QurbaniAnimal;
use App\Models\QurbaniOffering;
use App\Models\QurbaniSeason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A cow share at Rs 25,000, no limit.
 *
 * @extends Factory<QurbaniOffering>
 */
class QurbaniOfferingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => QurbaniSeason::factory(),
            'animal' => QurbaniAnimal::Cow,
            'price_per_share' => '25000.00',
        ];
    }

    public function goat(string $price = '60000.00'): static
    {
        return $this->state(['animal' => QurbaniAnimal::Goat, 'price_per_share' => $price]);
    }
}
