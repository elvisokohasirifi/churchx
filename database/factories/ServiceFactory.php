<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Service;
use App\ServiceScope;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['branch_id' => Branch::factory(), 'scope' => ServiceScope::Branch, 'name' => fake()->words(2, true), 'service_type' => 'sunday_service', 'date' => fake()->date(), 'start_time' => '09:00', 'location' => fake()->city()];
    }
}
