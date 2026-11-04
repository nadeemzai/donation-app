<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FundType;
use App\Models\Donation;
use App\Models\Fund;
use App\Services\FundLedgerService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fund>
 */
class FundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'F'.fake()->unique()->numerify('####'),
            'name' => fake()->unique()->words(2, true).' Fund',
            'type' => FundType::General,
            'restriction' => null,
            'description' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => 50,
        ];
    }

    public function zakat(): static
    {
        return $this->state(fn (array $attributes) => ['type' => FundType::Zakat, 'name' => 'Zakat']);
    }

    public function sadqa(): static
    {
        return $this->state(fn (array $attributes) => ['type' => FundType::Sadqa, 'name' => 'Sadqa']);
    }

    public function restricted(string $restriction = 'Qurbani animals and meat distribution only'): static
    {
        return $this->state(fn (array $attributes) => ['type' => FundType::Restricted, 'restriction' => $restriction]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    /**
     * A fund that already holds $amount, credited through the ledger (so the ledger stays
     * balanced), with a pending donation as the ledger source.
     */
    public function funded(string $amount = '10000000'): static
    {
        return $this->afterCreating(function (Fund $fund) use ($amount): void {
            $source = Donation::factory()->create(['fund_id' => $fund->id]);
            app(FundLedgerService::class)->credit($fund, $amount, $source, 'Opening balance (test)');
            $fund->refresh();
        });
    }
}
