<?php

namespace Database\Factories;

use App\Models\FinancialAccount;
use App\Models\Fund;
use App\Models\GivingType;
use App\Models\OfferingCollection;
use App\Models\OfferingItem;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfferingItem>
 */
class OfferingItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['offering_collection_id' => OfferingCollection::factory(), 'giving_type_id' => GivingType::factory(), 'fund_id' => Fund::factory(), 'payment_method_id' => PaymentMethod::factory(), 'financial_account_id' => FinancialAccount::factory(), 'amount' => '50.0000', 'currency' => 'GHS'];
    }
}
