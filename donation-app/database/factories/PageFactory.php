<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => 'page-'.fake()->unique()->numerify('####'),
            'title' => fake()->randomElement(['Our team', 'Annual report', 'Volunteer with us', 'FAQs']),
            'meta_description' => fake()->sentence(12),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'is_published' => true,
            'show_contact_form' => false,
            'show_in_footer' => false,
            'sort_order' => 10,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['is_published' => false]);
    }
}
