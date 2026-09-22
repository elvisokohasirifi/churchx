<?php

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('places ask data and help after the other sidebar pages', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');

    $response = $this->actingAs($administrator, 'backpack')->get(route('admin.dashboard'));

    $response->assertSeeInOrder([
        backpack_url('church-settings'),
        backpack_url('ask-data'),
        backpack_url('help'),
    ]);
});

it('shows administrators the complete help center', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');

    $response = $this->actingAs($administrator, 'backpack')->get(route('admin.help'));

    $response
        ->assertOk()
        ->assertSee('Help Center')
        ->assertSee('Members and branch membership')
        ->assertSee('Income and giving references')
        ->assertSee('Files, logs, audits, and backups')
        ->assertSee('Setup and dropdown reference data')
        ->assertSee('Required for a new user')
        ->assertSee('Add a member')
        ->assertSee('Create an expense')
        ->assertSee(backpack_url('help'));
});

it('limits finance officer help to finance-related guidance', function () {
    $financeOfficer = User::factory()->create();
    assignRole($financeOfficer, 'Finance Officer');

    $response = $this->actingAs($financeOfficer, 'backpack')->get(route('admin.help'));

    $response
        ->assertOk()
        ->assertSee('Income and giving references')
        ->assertSee('Expenses and approvals')
        ->assertSee('Account transfers and financial reports')
        ->assertDontSee('Members and branch membership')
        ->assertDontSee('Events')
        ->assertDontSee('Setup and dropdown reference data')
        ->assertDontSee('Files, logs, audits, and backups');
});

it('shows branch administrators branch-scoped operational tasks and income only', function () {
    $branchAdministrator = User::factory()->create();
    $branch = Branch::factory()->create();
    assignRole($branchAdministrator, 'Branch Administrator', $branch);

    $response = $this->actingAs($branchAdministrator, 'backpack')->get(route('admin.help'));

    $response
        ->assertOk()
        ->assertSee('Members and branch membership')
        ->assertSee('Attendance capture and register')
        ->assertSee('Departments and department members')
        ->assertSee('Record income')
        ->assertDontSee('Create an expense')
        ->assertDontSee('Account transfers and financial reports')
        ->assertDontSee('Setup and dropdown reference data');
});

it('removes modification tasks from church overseer help', function () {
    $overseer = User::factory()->create();
    assignRole($overseer, 'Church Overseer');

    $response = $this->actingAs($overseer, 'backpack')->get(route('admin.help'));

    $response
        ->assertOk()
        ->assertSee('Church and branches')
        ->assertSee('Members and branch membership')
        ->assertSee('View branches')
        ->assertDontSee('Add a branch')
        ->assertDontSee('Add a member')
        ->assertDontSee('Update a member')
        ->assertDontSee('Create an event');
});

it('requires an authenticated admin session for help', function () {
    $this->get(route('admin.help'))->assertRedirect();
});
