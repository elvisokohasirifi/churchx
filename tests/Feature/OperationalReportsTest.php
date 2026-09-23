<?php

use App\IncomeStatus;
use App\MemberBranchStatus;
use App\MemberStatus;
use App\Models\AttendanceSummary;
use App\Models\Branch;
use App\Models\BranchLeader;
use App\Models\Income;
use App\Models\LeadershipTitle;
use App\Models\Member;
use App\Models\MemberAttendance;
use App\Models\MemberBranch;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->travelTo('2026-09-22 12:00:00');
});

it('shows member attendance records only for accessible branches', function () {
    $visibleBranch = Branch::factory()->create(['name' => 'Visible Branch']);
    $hiddenBranch = Branch::factory()->create(['name' => 'Hidden Branch']);
    $user = User::factory()->create();
    assignRole($user, 'Branch Administrator', $visibleBranch);
    $visibleMember = memberInBranch($visibleBranch, ['first_name' => 'Visible', 'last_name' => 'Member']);
    $hiddenMember = memberInBranch($hiddenBranch, ['first_name' => 'Hidden', 'last_name' => 'Member']);
    $visibleService = Service::factory()->create(['branch_id' => $visibleBranch->id, 'date' => '2026-09-20']);
    $hiddenService = Service::factory()->create(['branch_id' => $hiddenBranch->id, 'date' => '2026-09-20']);
    MemberAttendance::query()->create(['service_id' => $visibleService->id, 'member_id' => $visibleMember->id, 'status' => 'present']);
    MemberAttendance::query()->create(['service_id' => $hiddenService->id, 'member_id' => $hiddenMember->id, 'status' => 'present']);

    $response = $this->actingAs($user, 'backpack')->get(route('admin.reports.attendance-records'));

    $response->assertOk()->assertSee('Visible Member')->assertDontSee('Hidden Member');
    expect($response->viewData('rows'))->toHaveCount(1)
        ->and($response->viewData('rows')->first()['present'])->toBe(1);
});

it('filters members by accessible branch and an allow-listed where clause', function () {
    $visibleBranch = Branch::factory()->create(['name' => 'Visible Branch']);
    $hiddenBranch = Branch::factory()->create(['name' => 'Hidden Branch']);
    $administrator = User::factory()->create();
    assignRole($administrator, 'Branch Administrator', $visibleBranch);
    memberInBranch($visibleBranch, ['first_name' => 'Ama', 'last_name' => 'Visible', 'membership_status' => MemberStatus::Member]);
    memberInBranch($visibleBranch, ['first_name' => 'Esi', 'last_name' => 'Inactive', 'membership_status' => MemberStatus::Inactive]);
    memberInBranch($hiddenBranch, ['first_name' => 'Hidden', 'last_name' => 'Member', 'membership_status' => MemberStatus::Member]);

    $response = $this->actingAs($administrator, 'backpack')->get(route('admin.reports.member-filter', [
        'branch_id' => $visibleBranch->id,
        'filter_field' => 'membership_status',
        'filter_operator' => '=',
        'filter_condition' => 'member',
    ]));

    $response->assertOk()
        ->assertSee('Ama Visible')
        ->assertDontSee('Esi Inactive')
        ->assertDontSee('Hidden Member');
    expect($response->viewData('members')->total())->toBe(1);
});

it('forbids filtering members through a branch outside the user scope', function () {
    $visibleBranch = Branch::factory()->create();
    $hiddenBranch = Branch::factory()->create();
    $administrator = User::factory()->create();
    assignRole($administrator, 'Branch Administrator', $visibleBranch);

    $this->actingAs($administrator, 'backpack')
        ->get(route('admin.reports.member-filter', ['branch_id' => $hiddenBranch->id]))
        ->assertForbidden();
});

it('rejects fields outside the member filter allow-list', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');

    $this->actingAs($administrator, 'backpack')
        ->from(route('admin.reports.member-filter'))
        ->get(route('admin.reports.member-filter', [
            'filter_field' => 'first_name) or 1 = 1',
            'filter_operator' => '=',
            'filter_condition' => 'Ama',
        ]))
        ->assertRedirect(route('admin.reports.member-filter'))
        ->assertSessionHasErrors('filter_field');
});

it('forbids the member filter report without member view access', function () {
    $financeOfficer = User::factory()->create();
    assignRole($financeOfficer, 'Finance Officer');

    $this->actingAs($financeOfficer, 'backpack')
        ->get(route('admin.reports.member-filter'))
        ->assertForbidden();
});

it('combines attendance from every branch led by the same leader', function () {
    $firstBranch = Branch::factory()->create(['name' => 'North Branch']);
    $secondBranch = Branch::factory()->create(['name' => 'South Branch']);
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    $leader = Member::factory()->create(['first_name' => 'Combined', 'last_name' => 'Leader']);
    $title = LeadershipTitle::query()->create(['name' => 'Branch Pastor']);
    foreach ([$firstBranch, $secondBranch] as $branch) {
        BranchLeader::query()->create([
            'branch_id' => $branch->id,
            'member_id' => $leader->id,
            'leadership_title_id' => $title->id,
            'start_date' => '2025-01-01',
            'is_active' => true,
        ]);
        $service = Service::factory()->create(['branch_id' => $branch->id, 'date' => '2026-09-20']);
        AttendanceSummary::factory()->create([
            'service_id' => $service->id,
            'branch_id' => $branch->id,
            'total_male' => 0,
            'total_female' => 0,
            'total_children' => 0,
            'total_members' => 25,
            'total_visitors' => 5,
        ]);
    }

    $response = $this->actingAs($administrator, 'backpack')->get(route('admin.reports.attendance-leaders'));

    $response->assertOk()->assertSee('Combined Leader')->assertSee('North Branch')->assertSee('South Branch');
    expect($response->viewData('rows'))->toHaveCount(1)
        ->and($response->viewData('rows')->first()['services'])->toBe(2)
        ->and($response->viewData('rows')->first()['total'])->toBe(60.0);
});

it('ranks churches using member and visitor attendance totals', function () {
    $branch = Branch::factory()->create(['name' => 'Register Branch']);
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    $service = Service::factory()->create(['branch_id' => $branch->id, 'date' => '2026-09-20']);
    AttendanceSummary::factory()->create([
        'service_id' => $service->id,
        'branch_id' => $branch->id,
        'total_male' => 0,
        'total_female' => 0,
        'total_children' => 0,
        'total_members' => 32,
        'total_visitors' => 5,
    ]);

    $response = $this->actingAs($administrator, 'backpack')->get(route('admin.reports.attendance-churches'));

    $response->assertOk()->assertSee('Register Branch');
    expect($response->viewData('rows'))->toHaveCount(1)
        ->and($response->viewData('rows')->first()['services'])->toBe(1)
        ->and($response->viewData('rows')->first()['attendance'])->toBe(37)
        ->and($response->viewData('rows')->first()['average'])->toBe(37.0);
});

it('groups completed income by service and excludes cancelled income', function () {
    $branch = Branch::factory()->create();
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    $service = Service::factory()->create(['branch_id' => $branch->id, 'name' => 'Harvest Service', 'date' => '2026-09-20']);
    Income::factory()->create(['branch_id' => $branch->id, 'service_id' => $service->id, 'amount' => '250.0000', 'date' => '2026-09-20', 'status' => IncomeStatus::Completed]);
    Income::factory()->create(['branch_id' => $branch->id, 'service_id' => $service->id, 'amount' => '999.0000', 'date' => '2026-09-20', 'status' => IncomeStatus::Cancelled]);

    $response = $this->actingAs($administrator, 'backpack')->get(route('admin.reports.giving-records'));

    $response->assertOk()->assertSee('Harvest Service')->assertSee('250.00')->assertDontSee('999.00');
    expect($response->viewData('rows'))->toHaveCount(1)
        ->and($response->viewData('rows')->first()['giving'])->toBe(250.0);
});

it('lists members whose absences meet the follow-up threshold', function () {
    $branch = Branch::factory()->create();
    $administrator = User::factory()->create();
    assignRole($administrator, 'Branch Administrator', $branch);
    $followUpMember = memberInBranch($branch, ['first_name' => 'Needs', 'last_name' => 'Followup']);
    $occasionalMember = memberInBranch($branch, ['first_name' => 'Occasional', 'last_name' => 'Absence']);

    foreach (['2026-09-07', '2026-09-14'] as $date) {
        $service = Service::factory()->create(['branch_id' => $branch->id, 'date' => $date]);
        MemberAttendance::query()->create(['service_id' => $service->id, 'member_id' => $followUpMember->id, 'status' => 'absent']);
    }
    $service = Service::factory()->create(['branch_id' => $branch->id, 'date' => '2026-09-21']);
    MemberAttendance::query()->create(['service_id' => $service->id, 'member_id' => $occasionalMember->id, 'status' => 'absent']);

    $response = $this->actingAs($administrator, 'backpack')->get(route('admin.reports.absence-follow-up'));

    $response->assertOk()->assertSee('Needs Followup')->assertDontSee('Occasional Absence');
    expect($response->viewData('rows'))->toHaveCount(1)
        ->and($response->viewData('rows')->first()['absent'])->toBe(2);
});

function memberInBranch(Branch $branch, array $attributes = []): Member
{
    $member = Member::factory()->create($attributes);
    MemberBranch::query()->create([
        'member_id' => $member->id,
        'branch_id' => $branch->id,
        'joined_date' => '2025-01-01',
        'is_primary' => true,
        'status' => MemberBranchStatus::Active,
    ]);

    return $member;
}
