<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\Vulnerability;
use App\Models\Beneficiary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A registered (not yet verified) household. Status and the Zakat decision are set by
 * the workflow, so tests that need them use forceFill or the service.
 *
 * @extends Factory<Beneficiary>
 */
class BeneficiaryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $household = fake()->numberBetween(2, 8);

        return [
            'name' => fake()->name('female'),
            'guardian_name' => fake()->name('male'),
            'gender' => Gender::Female,
            'cnic' => fake()->unique()->numerify('3520#-#######-#'),
            'phone' => '03'.fake()->unique()->numerify('#########'),
            'address' => fake()->streetAddress(),
            'city' => fake()->randomElement(['Lahore', 'Karachi', 'Multan', 'Faisalabad']),
            'area' => fake()->streetName(),
            'household_size' => $household,
            'dependents' => fake()->numberBetween(0, $household - 1),
            'monthly_income' => fake()->randomElement(['0.00', '8000.00', '15000.00', '22000.00']),
            'vulnerabilities' => [Vulnerability::Widow->value],
        ];
    }

    public function withoutCnic(): static
    {
        return $this->state(['cnic' => null]);
    }
}
