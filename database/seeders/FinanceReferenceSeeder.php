<?php

namespace Database\Seeders;

use App\Models\AssetCondition;
use App\Models\AssetStatusOption;
use App\Models\ExpenseType;
use App\Models\Fund;
use App\Models\GivingType;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class FinanceReferenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Cash', 'Mobile Money', 'Bank Transfer', 'Cheque', 'Card'] as $name) {
            PaymentMethod::query()->firstOrCreate(['name' => $name], ['is_active' => true]);
        }
        $funds = collect(['General Fund', 'Building Fund', 'Missions Fund', 'Welfare Fund'])->mapWithKeys(function (string $name): array {
            $fund = Fund::query()->firstOrCreate(['name' => $name], ['restricted' => $name !== 'General Fund', 'is_active' => true]);

            return [$name => $fund];
        });
        foreach (['Tithe', 'Offering', 'Thanksgiving', 'Missions', 'First Fruit', 'Seed'] as $name) {
            GivingType::query()->firstOrCreate(['name' => $name], ['default_fund_id' => $name === 'Missions' ? $funds['Missions Fund']->id : $funds['General Fund']->id, 'requires_giver' => $name !== 'Offering', 'is_active' => true]);
        }
        foreach (['Utilities', 'Transport', 'Welfare', 'Repairs', 'Rent', 'Media', 'Evangelism'] as $name) {
            ExpenseType::query()->firstOrCreate(['name' => $name], ['is_active' => true]);
        }
        foreach (['Excellent', 'Good', 'Fair', 'Needs Repair', 'Damaged'] as $name) {
            AssetCondition::query()->firstOrCreate(['name' => $name], ['is_active' => true]);
        }
        foreach (['active', 'assigned', 'in_storage', 'under_repair', 'lost', 'disposed'] as $name) {
            AssetStatusOption::query()->firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
