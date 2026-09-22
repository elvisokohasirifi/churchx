<?php

namespace Database\Factories;

use App\ExpenseStatus;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\FinancialAccount;
use App\Models\Fund;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['branch_id' => Branch::factory(), 'expense_type_id' => ExpenseType::factory(), 'fund_id' => Fund::factory(), 'payment_method_id' => PaymentMethod::factory(), 'financial_account_id' => FinancialAccount::factory(), 'requested_by' => User::factory(), 'recipient_name' => fake()->name(), 'amount' => '25.0000', 'currency' => 'GHS', 'description' => fake()->sentence(), 'date' => now()->toDateString(), 'status' => ExpenseStatus::Draft];
    }
}
