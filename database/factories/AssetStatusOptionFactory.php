<?php

namespace Database\Factories;

use App\Models\AssetStatusOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetStatusOption>
 */
class AssetStatusOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
