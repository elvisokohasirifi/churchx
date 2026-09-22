<?php

use App\MemberBranchStatus;
use App\Models\Branch;
use App\Models\BranchDepartment;
use App\Models\BranchDepartmentMember;
use App\Models\Church;
use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\Member;
use App\Models\MemberAttendance;
use App\Models\MemberBranch;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Church::factory()->create(['address' => '1 Church Avenue']);
});

it('keeps the church overseer read only', function () {
    $overseer = User::factory()->create();
    assignRole($overseer, 'Church Overseer');

    $this->actingAs($overseer, 'backpack')->get(route('branches.index'))->assertOk();
    $this->actingAs($overseer, 'backpack')->get(route('branches.create'))->assertForbidden();
    expect($overseer->fresh()->can('branches.manage'))->toBeFalse()
        ->and($overseer->fresh()->can('members.update'))->toBeFalse();
});

it('lets an app administrator impersonate and return from an active leader account', function () {
    $administrator = User::factory()->create();
    $leader = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    assignRole($leader, 'Church Leader');

    $this->actingAs($administrator, 'backpack')
        ->post(route('admin.users.impersonate', $leader))
        ->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($leader, 'backpack');
    $this->get(route('admin.dashboard'))->assertOk();
    $this->assertAuthenticatedAs($leader, 'backpack');

    $this->post(route('admin.impersonation.stop'))->assertRedirect(route('users.index'));
    $this->assertAuthenticatedAs($administrator, 'backpack');
    $this->get(route('users.index'))->assertOk();
    $this->assertAuthenticatedAs($administrator, 'backpack');
});

it('bulk assigns unique branch departments and department members', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    $branch = Branch::factory()->create();
    $department = Department::query()->create(['name' => 'Music']);
    $role = DepartmentRole::query()->create(['name' => 'Singer']);
    $member = Member::factory()->create();
    MemberBranch::query()->create(['member_id' => $member->id, 'branch_id' => $branch->id, 'joined_date' => today(), 'is_primary' => true, 'status' => MemberBranchStatus::Active]);

    $payload = ['branch_id' => $branch->id, 'department_ids' => [$department->id]];
    $this->actingAs($administrator, 'backpack')->post(route('admin.bulk.branch-departments'), $payload)->assertRedirect();
    $this->post(route('admin.bulk.branch-departments'), $payload)->assertRedirect();
    $branchDepartment = BranchDepartment::query()->sole();
    expect(BranchDepartment::query()->count())->toBe(1);

    $memberPayload = ['branch_department_id' => $branchDepartment->id, 'department_role_id' => $role->id, 'member_ids' => [$member->id]];
    $this->post(route('admin.bulk.department-members'), $memberPayload)->assertRedirect();
    $this->post(route('admin.bulk.department-members'), $memberPayload)->assertRedirect();
    expect(BranchDepartmentMember::query()->count())->toBe(1);
});

it('validates a branch and department pair before inserting a duplicate', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    $branch = Branch::factory()->create();
    $department = Department::query()->create(['name' => 'Protocol']);
    $payload = ['branch_id' => $branch->id, 'department_id' => $department->id, 'is_active' => 1];

    $this->actingAs($administrator, 'backpack')->post(route('branch-departments.store'), $payload)->assertRedirect();
    $this->post(route('branch-departments.store'), $payload)->assertSessionHasErrors('department_id');

    expect(BranchDepartment::query()->count())->toBe(1);
});

it('captures a complete branch attendance register with present and absent states', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    $branch = Branch::factory()->create();
    $service = Service::factory()->create(['branch_id' => $branch->id]);
    $present = Member::factory()->create(['gender' => 'male']);
    $absent = Member::factory()->create(['gender' => 'female']);
    foreach ([$present, $absent] as $member) {
        MemberBranch::query()->create(['member_id' => $member->id, 'branch_id' => $branch->id, 'joined_date' => today(), 'is_primary' => true, 'status' => MemberBranchStatus::Active]);
    }

    $this->actingAs($administrator, 'backpack')->post(route('admin.attendance.register.store'), [
        'branch_id' => $branch->id,
        'service_id' => $service->id,
        'attendance' => [$present->id => 'present', $absent->id => 'absent'],
    ])->assertRedirect();

    expect(MemberAttendance::query()->where('status', 'present')->count())->toBe(1)
        ->and(MemberAttendance::query()->where('status', 'absent')->count())->toBe(1);
    $this->assertDatabaseHas('attendance_summaries', ['service_id' => $service->id, 'branch_id' => $branch->id, 'total_members' => 1]);
});

it('shows configurable asset references and service choices', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');

    $this->actingAs($administrator, 'backpack')->get(route('asset-conditions.index'))->assertOk();
    $this->get(route('asset-statuses.index'))->assertOk();
    $this->get(route('services.create'))
        ->assertOk()
        ->assertSee('Church Wide')
        ->assertSee('Upcoming')
        ->assertSee('1 Church Avenue');
});

it('manages service types and only accepts active service type options', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    $branch = Branch::factory()->create();
    $serviceType = ServiceType::factory()->create(['name' => 'Sunday Worship']);
    $inactiveServiceType = ServiceType::factory()->create(['name' => 'Archived Service', 'is_active' => false]);
    $payload = [
        'branch_id' => $branch->id,
        'scope' => 'branch',
        'name' => 'First Service',
        'date' => today()->toDateString(),
        'location' => '1 Church Avenue',
        'status' => 'upcoming',
    ];

    $this->actingAs($administrator, 'backpack')->get(route('service-types.index'))->assertOk();
    $this->get(route('services.create'))->assertSee('Sunday Worship')->assertDontSee('Archived Service');
    $this->post(route('services.store'), [...$payload, 'service_type' => $inactiveServiceType->name])
        ->assertSessionHasErrors('service_type');
    $this->post(route('services.store'), [...$payload, 'service_type' => $serviceType->name])
        ->assertRedirect();

    $this->assertDatabaseHas('services', ['name' => 'First Service', 'service_type' => 'Sunday Worship']);
});

it('defaults a service date to the nearest sunday', function (string $currentDate, string $expectedDate) {
    $this->travelTo(new DateTimeImmutable($currentDate.' 09:00:00'));
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');

    $response = $this->actingAs($administrator, 'backpack')->get(route('services.create'));

    $response->assertSee('value="'.$expectedDate.'"', false);
    $this->travelBack();
})->with([
    'weekday uses next Sunday' => ['2026-09-21', '2026-09-27'],
    'Sunday uses today' => ['2026-09-27', '2026-09-27'],
]);

it('renders the bulk assignment and attendance register screens', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    Branch::factory()->create();

    $this->actingAs($administrator, 'backpack')->get(route('admin.bulk-assignments'))->assertOk()->assertSee('Bulk Assignments');
    $this->get(route('admin.attendance.register'))->assertOk()->assertSee('Attendance Register');
});

it('limits branch leader dropdowns and member choices to managed branches', function () {
    $managedBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $leader = User::factory()->create();
    assignRole($leader, 'App Administrator', $managedBranch);
    $managedMember = Member::factory()->create();
    $otherMember = Member::factory()->create();
    MemberBranch::query()->create(['member_id' => $managedMember->id, 'branch_id' => $managedBranch->id, 'joined_date' => today(), 'is_primary' => true, 'status' => MemberBranchStatus::Active]);
    MemberBranch::query()->create(['member_id' => $otherMember->id, 'branch_id' => $otherBranch->id, 'joined_date' => today(), 'is_primary' => true, 'status' => MemberBranchStatus::Active]);

    $response = $this->actingAs($leader, 'backpack')->get(route('branch-leaders.create'));

    $response->assertOk()
        ->assertSee($managedBranch->name)
        ->assertDontSee($otherBranch->name)
        ->assertSee($managedMember->full_name)
        ->assertDontSee($otherMember->full_name);
});

it('groups dropdown reference pages under a single setup sidebar section', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');

    $response = $this->actingAs($administrator, 'backpack')->get(route('admin.dashboard'));

    $response->assertSeeInOrder(['Setup', 'Church Settings']);
    foreach (['church-settings', 'service-types', 'departments', 'department-roles', 'leadership-titles', 'household-relationships', 'payment-methods', 'giving-types', 'funds', 'financial-accounts', 'expense-types', 'asset-types', 'asset-conditions', 'asset-statuses'] as $path) {
        expect(substr_count($response->getContent(), backpack_url($path)))->toBe(1);
    }
});

it('allows only app and church administrators to access setup pages', function (string $roleName, bool $allowed) {
    $user = User::factory()->create();
    assignRole($user, $roleName);

    $menuResponse = $this->actingAs($user, 'backpack')->get(route('admin.dashboard'));
    $setupResponse = $this->get(route('service-types.index'));

    if ($allowed) {
        $menuResponse->assertSee('Setup');
        $setupResponse->assertOk();

        return;
    }

    $menuResponse->assertDontSee('Church Settings');
    $menuResponse->assertDontSee(backpack_url('service-types'));
    $setupResponse->assertForbidden();
})->with([
    'app administrator' => ['App Administrator', true],
    'church administrator' => ['Church Administrator', true],
    'church overseer' => ['Church Overseer', false],
    'church leader' => ['Church Leader', false],
    'finance officer' => ['Finance Officer', false],
    'branch administrator' => ['Branch Administrator', false],
    'department leader' => ['Department Leader', false],
]);

it('groups users and role management under access control', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');

    $response = $this->actingAs($administrator, 'backpack')->get(route('admin.dashboard'));

    $response->assertSeeInOrder(['Access Control', 'Users', 'Roles', 'Role Assignments']);
    foreach (['users', 'roles', 'role-assignments'] as $path) {
        expect(substr_count($response->getContent(), backpack_url($path)))->toBe(1);
    }
});
