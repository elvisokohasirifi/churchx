<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\OfferingCollection;
use App\Models\Service;
use App\Models\User;
use App\OfferingCollectionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfferingCollection>
 */
class OfferingCollectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['service_id' => Service::factory(), 'branch_id' => Branch::factory(), 'date' => now()->toDateString(), 'counted_by' => User::factory(), 'status' => OfferingCollectionStatus::Draft];
    }
}
