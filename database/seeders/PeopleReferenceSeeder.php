<?php

namespace Database\Seeders;

use App\Models\HouseholdRelationship;
use App\Models\LeadershipTitle;
use Illuminate\Database\Seeder;

class PeopleReferenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Senior Pastor', 'Branch Pastor', 'Assistant Pastor', 'Elder', 'Deacon', 'Deaconess'] as $title) {
            LeadershipTitle::query()->firstOrCreate(['name' => $title]);
        }

        foreach (['Father', 'Mother', 'Husband', 'Wife', 'Son', 'Daughter', 'Guardian', 'Dependent'] as $relationship) {
            HouseholdRelationship::query()->firstOrCreate(['name' => $relationship]);
        }
    }
}
