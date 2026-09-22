<?php

namespace Database\Seeders;

use App\Services\RoleProvisioningService;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(RoleProvisioningService::class)->provision();
    }
}
