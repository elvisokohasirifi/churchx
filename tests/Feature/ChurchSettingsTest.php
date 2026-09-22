<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Church;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->church = Church::factory()->create(['name' => 'Old Name', 'currency' => 'USD', 'timezone' => 'UTC']);
});

it('allows a church administrator to update global settings', function () {
    $user = User::factory()->create();
    assignRole($user, 'Church Administrator');

    $response = $this->actingAs($user, 'backpack')->put(route('admin.church-settings.update'), [
        'name' => 'Grace Community Church',
        'email' => 'office@example.com',
        'currency' => 'gbp',
        'timezone' => 'Europe/London',
    ]);

    $response->assertRedirect();
    expect($this->church->fresh()->name)->toBe('Grace Community Church')
        ->and($this->church->fresh()->currency)->toBe('GBP')
        ->and(AuditLog::query()->where('action', 'church.settings.updated')->exists())->toBeTrue();
});

it('forbids a branch administrator from changing church-wide settings', function () {
    $user = User::factory()->create();
    assignRole($user, 'Branch Administrator', Branch::factory()->create());

    $response = $this->actingAs($user, 'backpack')->get(route('admin.church-settings.edit'));

    $response->assertForbidden();
});
