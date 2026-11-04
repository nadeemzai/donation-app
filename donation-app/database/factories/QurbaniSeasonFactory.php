<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QurbaniSeasonStatus;
use App\Models\Fund;
use App\Models\QurbaniSeason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A season open for booking, Eid 30 days away.
 *
 * @extends Factory<QurbaniSeason>
 */
class QurbaniSeasonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Eid ul Adha '.fake()->unique()->numberBetween(1447, 1600),
            'eid_date' => today()->addDays(30)->toDateString(),
            'fund_id' => Fund::factory()->restricted(),
            'status' => QurbaniSeasonStatus::Open,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => QurbaniSeasonStatus::Draft]);
    }

    public function eidInDays(int $days): static
    {
        return $this->state(['eid_date' => today()->addDays($days)->toDateString()]);
    }
}
