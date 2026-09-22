<?php

use App\IncomeStatus;
use App\MemberBranchStatus;
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
            'total_male' => 10,
            'total_female' => 15,
            'total_children' => 5,
        ]);
    }

    $response = $this->actingAs($administrator, 'backpack')->get(route('admin.reports.attendance-leaders'));

    $response->assertOk()->assertSee('Combined Leader')->assertSee('North Branch')->assertSee('South Branch');
    expect($response->viewData('rows'))->toHaveCount(1)
        ->and($response->viewData('rows')->first()['services'])->toBe(2)
        ->and($response->viewData('rows')->first()['total'])->toBe(60.0);
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
