<?php

namespace Database\Factories;

use App\Models\Church;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Church>
 */
class ChurchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Church',
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'website' => fake()->url(),
            'address' => fake()->address(),
            'country' => fake()->country(),
            'currency' => 'USD',
            'timezone' => 'UTC',
            'settings' => [],
        ];
    }
}
