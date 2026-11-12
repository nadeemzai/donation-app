<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->randomElement(['Winter is coming — keep a family warm', 'Your Zakat, delivered with care', 'Clean water changes everything']),
            'subtitle' => fake()->sentence(10),
            'button_label' => 'Donate now',
            'button_url' => '/campaigns',
            'text_align' => 'left',
            'sort_order' => fake()->numberBetween(1, 9),
            'is_active' => true,
        ];
    }
}
