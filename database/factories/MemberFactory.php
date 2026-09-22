<?php

namespace Database\Factories;

use App\MemberStatus;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'membership_number' => fake()->unique()->bothify('MEM-TST-######'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->unique()->e164PhoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
            'date_of_birth' => fake()->dateTimeBetween('-80 years', '-12 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['male', 'female']),
            'date_joined' => fake()->date(),
            'membership_status' => MemberStatus::Member,
        ];
    }
}
