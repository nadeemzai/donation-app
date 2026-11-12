<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ApplicationCategory;
use App\Models\Application;
use App\Models\Beneficiary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A freshly submitted application.
 *
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'beneficiary_id' => Beneficiary::factory(),
            'category' => ApplicationCategory::Ration,
            'description' => 'Monthly ration for the household.',
            'requested_amount' => '12000.00',
        ];
    }
}
