<?php

use App\Models\Branch;
use App\Models\BranchDepartment;
use App\Models\BranchDepartmentMember;
use App\Models\Broadcast;
use App\Models\BroadcastAudience;
use App\Models\ChurchGroup;
use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\Member;
use App\Models\User;
use App\Services\BroadcastAudienceResolver;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('deduplicates broadcast recipients across audiences', function () {
    $member = Member::factory()->create();
    $broadcast = Broadcast::query()->create(['title' => 'Notice', 'message' => 'Hello', 'channel' => 'email', 'created_by' => User::factory()->create()->id, 'status' => 'draft']);
    BroadcastAudience::query()->create(['broadcast_id' => $broadcast->id, 'audience_type' => 'all_members']);
    BroadcastAudience::query()->create(['broadcast_id' => $broadcast->id, 'audience_type' => 'member', 'audience_id' => $member->id]);

    expect(app(BroadcastAudienceResolver::class)->resolve($broadcast->load('audiences')))->toHaveCount(1);
});

it('shows branch-aware department and group selectors', function () {
    $user = User::factory()->create();
    assignRole($user, 'App Administrator');
    $branch = Branch::factory()->create(['name' => 'Central Branch']);
    $department = Department::query()->create(['name' => 'Worship']);
    BranchDepartment::query()->create([
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'is_active' => true,
    ]);
    ChurchGroup::query()->create([
        'name' => 'Young Adults',
        'type' => 'ministry',
        'branch_id' => $branch->id,
    ]);

    $response = $this->actingAs($user, 'backpack')->get(route('admin.broadcasts.create'));

    $response->assertOk()
        ->assertSee('Central Branch')
        ->assertSee('Worship')
        ->assertSee('Young Adults')
        ->assertSee('data-branch-id="'.$branch->id.'"', false);
});

it('shows member field, operator, and condition inputs for all-member audiences', function () {
    $user = User::factory()->create();
    assignRole($user, 'App Administrator');

    $response = $this->actingAs($user, 'backpack')->get(route('admin.broadcasts.create'));

    $response
        ->assertSee('name="filter_field"', false)
        ->assertSee('name="filter_operator"', false)
        ->assertSee('name="filter_value"', false)
        ->assertSee('Membership status')
        ->assertSee('Not in list')
        ->assertSee('Is empty / null');
});

it('stores an optional member filter for an all-member audience', function () {
    $user = User::factory()->create();
    assignRole($user, 'App Administrator');

    $response = $this->actingAs($user, 'backpack')->post(route('admin.broadcasts.store'), [
        'title' => 'Workers notice',
        'message' => 'Hello workers.',
        'channel' => 'email',
        'audience_type' => 'all_members',
        'filter_field' => 'membership_status',
        'filter_operator' => '=',
        'filter_value' => 'worker',
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('broadcast_audiences', [
        'audience_type' => 'all_members',
        'filter_field' => 'membership_status',
        'filter_operator' => '=',
        'filter_value' => 'worker',
    ]);
});

it('rejects unsafe or malformed member filters', function (array $filter, string $invalidField) {
    $user = User::factory()->create();
    assignRole($user, 'App Administrator');

    $response = $this->actingAs($user, 'backpack')->post(route('admin.broadcasts.store'), [
        'title' => 'Filtered notice',
        'message' => 'Hello.',
        'channel' => 'email',
        'audience_type' => 'all_members',
        ...$filter,
    ]);

    $response->assertSessionHasErrors($invalidField);
    $this->assertDatabaseCount('broadcasts', 0);
})->with([
    'unknown member field' => [[
        'filter_field' => 'first_name) OR 1=1 --',
        'filter_operator' => '=',
        'filter_value' => 'Alice',
    ], 'filter_field'],
    'unknown operator' => [[
        'filter_field' => 'first_name',
        'filter_operator' => 'OR 1=1',
        'filter_value' => 'Alice',
    ], 'filter_operator'],
    'incomplete range' => [[
        'filter_field' => 'date_joined',
        'filter_operator' => 'between',
        'filter_value' => '2025-01-01',
    ], 'filter_value'],
]);

it('resolves filtered all-member audiences', function (string $field, string $operator, ?string $condition, string $expectedMember) {
    $matchingMember = Member::factory()->create([
        'first_name' => 'Alice',
        'middle_name' => null,
        'last_name' => 'Stone',
        'gender' => 'female',
        'date_joined' => '2025-06-15',
        'membership_status' => 'worker',
    ]);
    $otherMember = Member::factory()->create([
        'first_name' => 'Bob',
        'middle_name' => 'James',
        'last_name' => 'Jones',
        'gender' => 'male',
        'date_joined' => '2024-06-15',
        'membership_status' => 'member',
    ]);
    $broadcast = Broadcast::query()->create([
        'title' => 'Filtered notice',
        'message' => 'Hello',
        'channel' => 'email',
        'created_by' => User::factory()->create()->id,
        'status' => 'draft',
    ]);
    BroadcastAudience::query()->create([
        'broadcast_id' => $broadcast->id,
        'audience_type' => 'all_members',
        'filter_field' => $field,
        'filter_operator' => $operator,
        'filter_value' => $condition,
    ]);

    $recipients = app(BroadcastAudienceResolver::class)->resolve($broadcast->load('audiences'));
    $expectedId = $expectedMember === 'matching' ? $matchingMember->id : $otherMember->id;

    expect($recipients->pluck('id')->all())->toBe([$expectedId]);
})->with([
    'equals' => ['gender', '=', 'female', 'matching'],
    'not equal' => ['gender', 'not', 'male', 'matching'],
    'greater than' => ['date_joined', '>', '2025-01-01', 'matching'],
    'less than' => ['date_joined', '<', '2025-01-01', 'other'],
    'in list' => ['first_name', 'in', 'Alice, Carol', 'matching'],
    'not in list' => ['first_name', 'not_in', 'Bob, Carol', 'matching'],
    'like' => ['last_name', 'like', '%Stone%', 'matching'],
    'not like' => ['last_name', 'not_like', '%Jones%', 'matching'],
    'between' => ['date_joined', 'between', '2025-01-01, 2025-12-31', 'matching'],
    'not between' => ['date_joined', 'not_between', '2025-01-01, 2025-12-31', 'other'],
    'is null' => ['middle_name', 'is_null', null, 'matching'],
    'is not null' => ['middle_name', 'is_not_null', null, 'other'],
]);

it('stores the selected branch context for each targeted audience type', function (string $audienceType) {
    $user = User::factory()->create();
    assignRole($user, 'App Administrator');
    $branch = Branch::factory()->create();
    $department = Department::query()->create(['name' => 'Children']);
    BranchDepartment::query()->create([
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'is_active' => true,
    ]);
    $group = ChurchGroup::query()->create([
        'name' => 'Leaders Fellowship',
        'type' => 'fellowship',
        'branch_id' => $branch->id,
    ]);
    $targetId = match ($audienceType) {
        'branch' => $branch->id,
        'department' => $department->id,
        'group' => $group->id,
    };

    $response = $this->actingAs($user, 'backpack')->post(route('admin.broadcasts.store'), [
        'title' => 'Branch notice',
        'message' => 'Please take note.',
        'channel' => 'email',
        'audience_type' => $audienceType,
        'branch_id' => $branch->id,
        'department_id' => $audienceType === 'department' ? $department->id : null,
        'group_id' => $audienceType === 'group' ? $group->id : null,
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('broadcast_audiences', [
        'audience_type' => $audienceType,
        'audience_id' => $targetId,
        'branch_id' => $branch->id,
    ]);
})->with(['branch', 'department', 'group']);

it('rejects a department or group from a different branch', function (string $audienceType) {
    $user = User::factory()->create();
    assignRole($user, 'App Administrator');
    $selectedBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $department = Department::query()->create(['name' => 'Outreach']);
    BranchDepartment::query()->create([
        'branch_id' => $otherBranch->id,
        'department_id' => $department->id,
        'is_active' => true,
    ]);
    $group = ChurchGroup::query()->create([
        'name' => 'Other Branch Group',
        'type' => 'cell',
        'branch_id' => $otherBranch->id,
    ]);

    $response = $this->actingAs($user, 'backpack')->post(route('admin.broadcasts.store'), [
        'title' => 'Scoped notice',
        'message' => 'Please take note.',
        'channel' => 'email',
        'audience_type' => $audienceType,
        'branch_id' => $selectedBranch->id,
        'department_id' => $audienceType === 'department' ? $department->id : null,
        'group_id' => $audienceType === 'group' ? $group->id : null,
    ]);

    $response->assertSessionHasErrors($audienceType.'_id');
    $this->assertDatabaseCount('broadcasts', 0);
})->with(['department', 'group']);

it('resolves a department audience only within its selected branch', function () {
    $firstBranch = Branch::factory()->create();
    $secondBranch = Branch::factory()->create();
    $department = Department::query()->create(['name' => 'Hospitality']);
    $firstBranchDepartment = BranchDepartment::query()->create([
        'branch_id' => $firstBranch->id,
        'department_id' => $department->id,
        'is_active' => true,
    ]);
    $secondBranchDepartment = BranchDepartment::query()->create([
        'branch_id' => $secondBranch->id,
        'department_id' => $department->id,
        'is_active' => true,
    ]);
    $departmentRole = DepartmentRole::query()->create(['name' => 'Member']);
    $includedMember = Member::factory()->create();
    $excludedMember = Member::factory()->create();
    BranchDepartmentMember::query()->create([
        'branch_department_id' => $firstBranchDepartment->id,
        'member_id' => $includedMember->id,
        'department_role_id' => $departmentRole->id,
        'is_active' => true,
    ]);
    BranchDepartmentMember::query()->create([
        'branch_department_id' => $secondBranchDepartment->id,
        'member_id' => $excludedMember->id,
        'department_role_id' => $departmentRole->id,
        'is_active' => true,
    ]);
    $broadcast = Broadcast::query()->create([
        'title' => 'Department notice',
        'message' => 'Hello',
        'channel' => 'email',
        'created_by' => User::factory()->create()->id,
        'status' => 'draft',
    ]);
    BroadcastAudience::query()->create([
        'broadcast_id' => $broadcast->id,
        'audience_type' => 'department',
        'audience_id' => $department->id,
        'branch_id' => $firstBranch->id,
    ]);

    $recipients = app(BroadcastAudienceResolver::class)->resolve($broadcast->load('audiences'));

    expect($recipients->pluck('id')->all())->toBe([$includedMember->id]);
});
