<?php

namespace Database\Factories;

use App\Models\Fund;
use App\Models\GivingType;
use App\Models\Member;
use App\Models\Pledge;
use App\PledgeStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pledge>
 */
class PledgeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['member_id' => Member::factory(), 'giving_type_id' => GivingType::factory(), 'fund_id' => Fund::factory(), 'pledged_amount' => '500.0000', 'due_date' => now()->addMonth()->toDateString(), 'status' => PledgeStatus::Active];
    }
}
