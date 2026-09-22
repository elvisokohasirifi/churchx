<?php

namespace Database\Factories;

use App\IncomeStatus;
use App\Models\Branch;
use App\Models\FinancialAccount;
use App\Models\Fund;
use App\Models\GivingType;
use App\Models\Income;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Income>
 */
class IncomeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['branch_id' => Branch::factory(), 'giving_type_id' => GivingType::factory(), 'fund_id' => Fund::factory(), 'payment_method_id' => PaymentMethod::factory(), 'financial_account_id' => FinancialAccount::factory(), 'amount' => '100.0000', 'currency' => 'GHS', 'date' => now()->toDateString(), 'recorded_by' => User::factory(), 'receipt_number' => fake()->unique()->bothify('REC-########'), 'status' => IncomeStatus::Completed];
    }
}
