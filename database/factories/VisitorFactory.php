<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visitor>
 */
class VisitorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->unique()->e164PhoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
            'gender' => fake()->randomElement(['male', 'female']),
            'branch_id' => Branch::factory(),
            'first_visit_date' => fake()->date(),
        ];
    }
}
