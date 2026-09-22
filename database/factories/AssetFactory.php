<?php

namespace Database\Factories;

use App\AssetStatus;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['branch_id' => Branch::factory(), 'name' => fake()->words(2, true), 'asset_type_id' => AssetType::query()->firstOrCreate(['name' => 'Equipment'])->id, 'purchase_price' => '1000.0000', 'currency' => 'GHS', 'quantity' => 1, 'status' => AssetStatus::Active];
    }
}
