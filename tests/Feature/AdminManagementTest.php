<?php

use App\MemberBranchStatus;
use App\Models\Branch;
use App\Models\BranchLeader;
use App\Models\Church;
use App\Models\LeadershipTitle;
use App\Models\Member;
use App\Models\MemberBranch;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Church::factory()->create();
});

it('renders phase one management screens for an app administrator', function (string $routeName) {
    $user = User::factory()->create();
    assignRole($user, 'App Administrator');

    $response = $this->actingAs($user, 'backpack')->get(route($routeName));

    $response->assertOk();
})->with([
    'branches' => 'branches.index',
    'users' => 'users.index',
    'roles' => 'roles.index',
    'role assignments' => 'role-assignments.index',
]);

it('shows role scope and attached branches on the user preview', function () {
    $viewer = User::factory()->create();
    assignRole($viewer, 'App Administrator');
    $target = User::factory()->create(['name' => 'Scoped Leader']);
    $branch = Branch::factory()->create(['name' => 'North <script>alert(1)</script>']);
    assignRole($target, 'Branch Administrator', $branch);
    assignRole($target, 'Church Overseer');

    $response = $this->actingAs($viewer, 'backpack')->get(route('users.show', $target));

    $response
        ->assertSee('Roles')
        ->assertSee('Scope (Attached Branches)')
        ->assertSee('Branch Administrator')
        ->assertSee('North &lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertSee('Church Overseer')
        ->assertSee('Church-wide')
        ->assertDontSee('North <script>alert(1)</script>', false);
});

it('shows active roles on the users listing', function () {
    $viewer = User::factory()->create();
    assignRole($viewer, 'App Administrator');
    $target = User::factory()->create(['name' => 'Listed Scoped Leader']);
    assignRole($target, 'Branch Administrator', Branch::factory()->create());

    $response = $this->actingAs($viewer, 'backpack')->post(route('users.search'), [
        'start' => 0,
        'length' => 25,
    ]);

    $response->assertOk()
        ->assertSee('Listed Scoped Leader')
        ->assertSee('Branch Administrator');
});

it('assigns a role to multiple branch scopes at once', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    $target = User::factory()->create();
    $role = Role::query()->where('name', 'Branch Administrator')->firstOrFail();
    $firstBranch = Branch::factory()->create();
    $secondBranch = Branch::factory()->create();
    Notification::fake();

    $response = $this->actingAs($administrator, 'backpack')->post(route('role-assignments.store'), [
        'user_id' => $target->id,
        'role_id' => $role->id,
        'branch_ids' => [$firstBranch->id, $secondBranch->id],
        'is_active' => true,
        'save_action' => 'save_and_back',
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('user_roles', ['user_id' => $target->id, 'role_id' => $role->id, 'branch_id' => $firstBranch->id]);
    $this->assertDatabaseHas('user_roles', ['user_id' => $target->id, 'role_id' => $role->id, 'branch_id' => $secondBranch->id]);
});

it('uses church-wide scope when no branches are selected for a role', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    $target = User::factory()->create();
    $role = Role::query()->where('name', 'Church Overseer')->firstOrFail();
    Notification::fake();

    $response = $this->actingAs($administrator, 'backpack')->post(route('role-assignments.store'), [
        'user_id' => $target->id,
        'role_id' => $role->id,
        'branch_ids' => [],
        'is_active' => true,
        'save_action' => 'save_and_back',
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('user_roles', ['user_id' => $target->id, 'role_id' => $role->id, 'branch_id' => null]);
});

it('shows and synchronizes multiple branch scopes while editing a role assignment', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    $target = User::factory()->create();
    $role = Role::query()->where('name', 'Branch Administrator')->firstOrFail();
    $removedBranch = Branch::factory()->create();
    $retainedBranch = Branch::factory()->create();
    $addedBranch = Branch::factory()->create();
    $assignment = assignRole($target, 'Branch Administrator', $removedBranch);
    assignRole($target, 'Branch Administrator', $retainedBranch);
    Notification::fake();

    $editResponse = $this->actingAs($administrator, 'backpack')->get(route('role-assignments.edit', $assignment));

    $editResponse
        ->assertSee('name="branch_ids[]"', false)
        ->assertSee('multiple', false)
        ->assertSee('data-clear-multiselect', false)
        ->assertSee('Clear all')
        ->assertSee('value="'.$removedBranch->id.'" selected', false)
        ->assertSee('value="'.$retainedBranch->id.'" selected', false);

    $updateResponse = $this->actingAs($administrator, 'backpack')->put(route('role-assignments.update', $assignment), [
        'id' => $assignment->id,
        'user_id' => $target->id,
        'role_id' => $role->id,
        'branch_ids' => [$retainedBranch->id, $addedBranch->id],
        'is_active' => true,
        'save_action' => 'save_and_back',
    ]);

    $updateResponse->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('user_roles', ['user_id' => $target->id, 'role_id' => $role->id, 'branch_id' => $removedBranch->id]);
    $this->assertDatabaseHas('user_roles', ['user_id' => $target->id, 'role_id' => $role->id, 'branch_id' => $retainedBranch->id]);
    $this->assertDatabaseHas('user_roles', ['user_id' => $target->id, 'role_id' => $role->id, 'branch_id' => $addedBranch->id]);
    expect(UserRole::query()->where('user_id', $target->id)->where('role_id', $role->id)->count())->toBe(2);
});

it('does not reveal another branch through a branch-scoped admin route', function () {
    $assignedBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $user = User::factory()->create();
    assignRole($user, 'Branch Administrator', $assignedBranch);

    $allowedResponse = $this->actingAs($user, 'backpack')->get(route('branches.show', $assignedBranch));
    $isolatedResponse = $this->actingAs($user, 'backpack')->get(route('branches.show', $otherBranch));

    $allowedResponse->assertOk();
    $isolatedResponse->assertNotFound();
});

it('creates a branch together with its leaders', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    $member = Member::factory()->create();
    $title = LeadershipTitle::query()->create(['name' => 'Resident Pastor']);

    $response = $this->actingAs($administrator, 'backpack')->post(route('branches.store'), [
        'name' => 'North Campus',
        'code' => 'NORTH',
        'status' => 'active',
        'leaders' => [[
            'member_id' => $member->id,
            'leadership_title_id' => $title->id,
            'start_date' => '2026-09-21',
            'end_date' => null,
            'is_active' => '1',
        ]],
    ]);

    $branch = Branch::query()->where('code', 'NORTH')->firstOrFail();
    $response->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('branch_leaders', [
        'branch_id' => $branch->id,
        'member_id' => $member->id,
        'leadership_title_id' => $title->id,
        'is_active' => true,
    ]);
});

it('shows saved leaders while editing a branch', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    $branch = Branch::factory()->create();
    $member = Member::factory()->create(['first_name' => 'Ama', 'last_name' => 'Mensah']);
    $title = LeadershipTitle::query()->create(['name' => 'Assistant Pastor']);
    BranchLeader::query()->create([
        'branch_id' => $branch->id,
        'member_id' => $member->id,
        'leadership_title_id' => $title->id,
        'start_date' => '2026-09-21',
        'is_active' => true,
    ]);

    $response = $this->actingAs($administrator, 'backpack')->get(route('branches.edit', $branch));

    $response->assertOk()
        ->assertSee('Add leader')
        ->assertSee('Ama Mensah')
        ->assertSee('Assistant Pastor')
        ->assertSee('2026-09-21');
});

it('updates, adds, and removes leaders while editing a branch', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    $branch = Branch::factory()->create();
    $title = LeadershipTitle::query()->create(['name' => 'Branch Pastor']);
    $existingMember = Member::factory()->create();
    $newMember = Member::factory()->create();
    $removedMember = Member::factory()->create();
    $existingLeader = BranchLeader::query()->create([
        'branch_id' => $branch->id,
        'member_id' => $existingMember->id,
        'leadership_title_id' => $title->id,
        'start_date' => '2026-01-01',
        'is_active' => true,
    ]);
    $removedLeader = BranchLeader::query()->create([
        'branch_id' => $branch->id,
        'member_id' => $removedMember->id,
        'leadership_title_id' => $title->id,
        'start_date' => '2026-01-01',
        'is_active' => true,
    ]);

    $response = $this->actingAs($administrator, 'backpack')->put(route('branches.update', $branch), [
        'id' => $branch->id,
        'name' => $branch->name,
        'code' => $branch->code,
        'status' => 'active',
        'leaders' => [
            [
                'id' => $existingLeader->id,
                'member_id' => $existingMember->id,
                'leadership_title_id' => $title->id,
                'start_date' => '2026-01-01',
                'end_date' => '2026-09-21',
                'is_active' => '0',
            ],
            [
                'member_id' => $newMember->id,
                'leadership_title_id' => $title->id,
                'start_date' => '2026-09-21',
                'end_date' => null,
                'is_active' => '1',
            ],
        ],
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();
    expect($existingLeader->refresh()->end_date?->toDateString())->toBe('2026-09-21')
        ->and($existingLeader->is_active)->toBeFalse();
    $this->assertDatabaseHas('branch_leaders', ['branch_id' => $branch->id, 'member_id' => $newMember->id]);
    $this->assertDatabaseMissing('branch_leaders', ['id' => $removedLeader->id]);
});

it('rejects duplicate leader and title combinations on a branch', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    $member = Member::factory()->create();
    $title = LeadershipTitle::query()->create(['name' => 'Campus Coordinator']);
    $leader = [
        'member_id' => $member->id,
        'leadership_title_id' => $title->id,
        'start_date' => '2026-09-21',
        'end_date' => null,
        'is_active' => '1',
    ];

    $response = $this->actingAs($administrator, 'backpack')->post(route('branches.store'), [
        'name' => 'Duplicate Test Branch',
        'code' => 'DUP',
        'status' => 'active',
        'leaders' => [$leader, $leader],
    ]);

    $response->assertSessionHasErrors('leaders.1.member_id');
    $this->assertDatabaseMissing('branches', ['code' => 'DUP']);
});

it('prevents scoped administrators from assigning leaders from another branch', function () {
    $managedBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator', $managedBranch);
    $otherMember = Member::factory()->create();
    MemberBranch::query()->create([
        'member_id' => $otherMember->id,
        'branch_id' => $otherBranch->id,
        'joined_date' => '2026-09-21',
        'is_primary' => true,
        'status' => MemberBranchStatus::Active,
    ]);
    $title = LeadershipTitle::query()->create(['name' => 'Branch Coordinator']);

    $response = $this->actingAs($administrator, 'backpack')->put(route('branches.update', $managedBranch), [
        'id' => $managedBranch->id,
        'name' => $managedBranch->name,
        'code' => $managedBranch->code,
        'status' => 'active',
        'leaders' => [[
            'member_id' => $otherMember->id,
            'leadership_title_id' => $title->id,
            'start_date' => '2026-09-21',
            'end_date' => null,
            'is_active' => '1',
        ]],
    ]);

    $response->assertSessionHasErrors('leaders.0.member_id');
    $this->assertDatabaseMissing('branch_leaders', ['branch_id' => $managedBranch->id, 'member_id' => $otherMember->id]);
});
