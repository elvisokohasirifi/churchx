<?php

namespace Database\Factories;

use App\AccountTransferStatus;
use App\Models\AccountTransfer;
use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountTransfer>
 */
class AccountTransferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['from_account_id' => FinancialAccount::factory(), 'to_account_id' => FinancialAccount::factory(), 'amount' => '50.0000', 'currency' => 'GHS', 'date' => now()->toDateString(), 'initiated_by' => User::factory(), 'status' => AccountTransferStatus::PendingApproval];
    }
}
