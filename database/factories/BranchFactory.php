<?php

namespace Database\Factories;

use App\BranchStatus;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' Branch',
            'code' => fake()->unique()->bothify('BR-###'),
            'address' => fake()->address(),
            'location' => fake()->city(),
            'date_started' => fake()->date(),
            'status' => BranchStatus::Active,
        ];
    }
}
