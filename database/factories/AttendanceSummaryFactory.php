<?php

namespace Database\Factories;

use App\Models\AttendanceSummary;
use App\Models\Branch;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceSummary>
 */
class AttendanceSummaryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['service_id' => Service::factory(), 'branch_id' => Branch::factory(), 'total_male' => 20, 'total_female' => 25, 'total_children' => 10, 'total_members' => 45, 'total_visitors' => 10, 'captured_by' => User::factory()];
    }
}
