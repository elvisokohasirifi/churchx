<?php

namespace Database\Factories;

use App\Models\LeadershipTitle;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneLeader;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ZoneLeader>
 */
class ZoneLeaderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'zone_id' => Zone::factory(),
            'user_id' => User::factory(),
            'leadership_title_id' => LeadershipTitle::query()->firstOrCreate(['name' => 'Zone Leader'])->id,
            'start_date' => fake()->date(),
            'is_active' => true,
        ];
    }
}
