<?php

namespace Database\Factories;

use App\Models\Fund;
use App\Models\GivingType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GivingType>
 */
class GivingTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['name' => fake()->unique()->word(), 'requires_giver' => false, 'default_fund_id' => Fund::factory(), 'is_active' => true];
    }
}
