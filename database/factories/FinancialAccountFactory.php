<?php

namespace Database\Factories;

use App\FinancialAccountType;
use App\Models\Branch;
use App\Models\FinancialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialAccount>
 */
class FinancialAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['branch_id' => Branch::factory(), 'name' => fake()->unique()->words(3, true), 'type' => FinancialAccountType::Cash, 'currency' => 'GHS', 'opening_balance' => '0.0000', 'is_active' => true];
    }
}
