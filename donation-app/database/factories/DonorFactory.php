<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DonorType;
use App\Models\Donor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Donor>
 */
class DonorFactory extends Factory
{
    protected static ?string $password;

    /**
     * Default: a registered individual donor.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '03'.fake()->unique()->numerify('#########'),
            'type' => DonorType::Individual,
            'city' => fake()->randomElement(['Lahore', 'Karachi', 'Islamabad', 'Rawalpindi', 'Faisalabad', 'Multan', 'Peshawar']),
            'password' => static::$password ??= Hash::make('password'),
            'preferences' => ['whatsapp_opt_in' => true, 'email_opt_in' => true],
            'remember_token' => Str::random(10),
        ];
    }

    /** Gave without creating an account. */
    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
            'remember_token' => null,
        ]);
    }

    public function registered(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
    }

    public function corporate(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DonorType::Corporate,
            'name' => fake()->company(),
        ]);
    }
}
