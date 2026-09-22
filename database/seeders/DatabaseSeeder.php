<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleAndPermissionSeeder::class);
        $this->call(PeopleReferenceSeeder::class);
        $this->call(OperationalReferenceSeeder::class);
        $this->call(FinanceReferenceSeeder::class);
    }
}
