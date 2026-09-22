<?php

namespace Database\Factories;

use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' Zone',
            'code' => fake()->unique()->bothify('ZN-###'),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
