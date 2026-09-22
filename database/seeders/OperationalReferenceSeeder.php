<?php

namespace Database\Seeders;

use App\Models\AssetType;
use App\Models\DepartmentRole;
use App\Models\ServiceType;
use Illuminate\Database\Seeder;

class OperationalReferenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Leader', 'Assistant Leader', 'Secretary', 'Member'] as $name) {
            DepartmentRole::query()->firstOrCreate(['name' => $name]);
        }
        foreach (['Building', 'Vehicle', 'Equipment', 'Furniture', 'Electronics'] as $name) {
            AssetType::query()->firstOrCreate(['name' => $name]);
        }
        foreach (['sunday_service', 'midweek_service', 'prayer_meeting', 'special_service', 'conference'] as $name) {
            ServiceType::query()->firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
