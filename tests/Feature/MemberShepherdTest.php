<?php

use App\MemberBranchStatus;
use App\MemberStatus;
use App\Models\Branch;
use App\Models\BranchLeader;
use App\Models\LeadershipTitle;
use App\Models\Member;
use App\Models\MemberBranch;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('shows the shepherd full name instead of the shepherd id on the member listing', function () {
    $branch = Branch::factory()->create();
    $shepherd = Member::factory()->create(['first_name' => 'Grace', 'middle_name' => 'Ama', 'last_name' => 'Mensah']);
    $member = Member::factory()->create(['shepherd_id' => $shepherd->id]);
    MemberBranch::query()->create([
        'member_id' => $member->id,
        'branch_id' => $branch->id,
        'joined_date' => today(),
        'is_primary' => true,
        'status' => MemberBranchStatus::Active,
    ]);
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');

    $response = $this->actingAs($administrator, 'backpack')->post(route('members.search'), [
        'draw' => 1,
        'start' => 0,
        'length' => 25,
    ]);

    $response->assertOk()
        ->assertSee('Grace Ama Mensah')
        ->assertDontSee($shepherd->id);
});

it('backfills initial members when the first branch leader is appointed', function () {
    $branch = Branch::factory()->create();
    $initialMember = Member::factory()->create();
    MemberBranch::query()->create([
        'member_id' => $initialMember->id,
        'branch_id' => $branch->id,
        'joined_date' => today(),
        'is_primary' => true,
        'status' => MemberBranchStatus::Active,
    ]);

    $branchLeader = BranchLeader::query()->create([
        'branch_id' => $branch->id,
        'member_id' => $initialMember->id,
        'leadership_title_id' => LeadershipTitle::query()->create(['name' => 'Founding Pastor'])->id,
        'start_date' => today(),
        'is_active' => true,
    ]);

    expect($initialMember->fresh()->branch_leader_id)->toBe($branchLeader->id);
});

it('defaults a new member to the first active branch leader', function () {
    $branch = Branch::factory()->create();
    $firstLeaderMember = Member::factory()->create();
    $secondLeaderMember = Member::factory()->create();
    foreach ([$firstLeaderMember, $secondLeaderMember] as $leaderMember) {
        MemberBranch::query()->create([
            'member_id' => $leaderMember->id,
            'branch_id' => $branch->id,
            'joined_date' => today(),
            'is_primary' => true,
            'status' => MemberBranchStatus::Active,
        ]);
    }
    $title = LeadershipTitle::query()->create(['name' => 'Default Leader']);
    $firstLeader = BranchLeader::query()->create([
        'branch_id' => $branch->id,
        'member_id' => $firstLeaderMember->id,
        'leadership_title_id' => $title->id,
        'start_date' => today()->subYear(),
        'is_active' => true,
    ]);
    BranchLeader::query()->create([
        'branch_id' => $branch->id,
        'member_id' => $secondLeaderMember->id,
        'leadership_title_id' => $title->id,
        'start_date' => today()->subMonth(),
        'is_active' => true,
    ]);
    $admin = User::factory()->create();
    assignRole($admin, 'App Administrator');

    $response = $this->actingAs($admin, 'backpack')->post(route('members.store'), [
        'primary_branch_id' => $branch->id,
        'first_name' => 'Defaulted',
        'last_name' => 'Member',
        'membership_status' => MemberStatus::Member->value,
    ]);

    $member = Member::query()->where('first_name', 'Defaulted')->firstOrFail();
    $response->assertRedirect()->assertSessionHasNoErrors();
    expect($member->branch_leader_id)->toBe($firstLeader->id);
});

it('allows an explicitly empty branch leader when active leaders exist', function () {
    $branch = Branch::factory()->create();
    $leaderMember = Member::factory()->create();
    MemberBranch::query()->create([
        'member_id' => $leaderMember->id,
        'branch_id' => $branch->id,
        'joined_date' => today(),
        'is_primary' => true,
        'status' => MemberBranchStatus::Active,
    ]);
    BranchLeader::query()->create([
        'branch_id' => $branch->id,
        'member_id' => $leaderMember->id,
        'leadership_title_id' => LeadershipTitle::query()->create(['name' => 'Optional Leader'])->id,
        'start_date' => today(),
        'is_active' => true,
    ]);
    $admin = User::factory()->create();
    assignRole($admin, 'App Administrator');

    $response = $this->actingAs($admin, 'backpack')->post(route('members.store'), [
        'primary_branch_id' => $branch->id,
        'branch_leader_id' => '',
        'first_name' => 'Unassigned',
        'last_name' => 'Member',
        'membership_status' => MemberStatus::Member->value,
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();
    $member = Member::query()->where('first_name', 'Unassigned')->where('last_name', 'Member')->firstOrFail();

    expect($member->branch_leader_id)->toBeNull();
});

it('creates a member with a distinct branch leader and shepherd', function () {
    $branch = Branch::factory()->create();
    $leaderMember = Member::factory()->create();
    $shepherd = Member::factory()->create();
    foreach ([$leaderMember, $shepherd] as $existingMember) {
        MemberBranch::query()->create([
            'member_id' => $existingMember->id,
            'branch_id' => $branch->id,
            'joined_date' => today(),
            'is_primary' => true,
            'status' => MemberBranchStatus::Active,
        ]);
    }
    $branchLeader = BranchLeader::query()->create([
        'branch_id' => $branch->id,
        'member_id' => $leaderMember->id,
        'leadership_title_id' => LeadershipTitle::query()->create(['name' => 'Senior Branch Pastor'])->id,
        'start_date' => today(),
        'is_active' => true,
    ]);
    $admin = User::factory()->create();
    assignRole($admin, 'App Administrator');

    $response = $this->actingAs($admin, 'backpack')->post(route('members.store'), [
        'primary_branch_id' => $branch->id,
        'branch_leader_id' => $branchLeader->id,
        'shepherd_id' => $shepherd->id,
        'first_name' => 'Esi',
        'last_name' => 'Arthur',
        'membership_status' => MemberStatus::Member->value,
    ]);

    $member = Member::query()->where('first_name', 'Esi')->where('last_name', 'Arthur')->firstOrFail();
    $response->assertRedirect()->assertSessionHasNoErrors();
    expect($member->branch_leader_id)->toBe($branchLeader->id)
        ->and($member->shepherd_id)->toBe($shepherd->id)
        ->and($member->branchLeader->member->is($leaderMember))->toBeTrue();
});

it('assigns a same-branch member as a shepherd', function () {
    $branch = Branch::factory()->create();
    $shepherd = Member::factory()->create();
    $member = Member::factory()->create();

    foreach ([$shepherd, $member] as $branchMember) {
        MemberBranch::query()->create([
            'member_id' => $branchMember->id,
            'branch_id' => $branch->id,
            'joined_date' => today(),
            'is_primary' => true,
            'status' => MemberBranchStatus::Active,
        ]);
    }
    $branchLeaderMember = Member::factory()->create();
    MemberBranch::query()->create([
        'member_id' => $branchLeaderMember->id,
        'branch_id' => $branch->id,
        'joined_date' => today(),
        'is_primary' => true,
        'status' => MemberBranchStatus::Active,
    ]);
    $branchLeader = BranchLeader::query()->create([
        'branch_id' => $branch->id,
        'member_id' => $branchLeaderMember->id,
        'leadership_title_id' => LeadershipTitle::query()->create(['name' => 'Branch Pastor'])->id,
        'start_date' => today(),
        'is_active' => true,
    ]);

    $admin = User::factory()->create();
    assignRole($admin, 'App Administrator');

    $response = $this->actingAs($admin, 'backpack')->put(route('members.update', $member->id), [
        'shepherd_id' => $shepherd->id,
        'branch_leader_id' => $branchLeader->id,
        'first_name' => $member->first_name,
        'last_name' => $member->last_name,
        'membership_status' => MemberStatus::Member->value,
    ]);

    $response->assertSessionHasNoErrors();
    expect($member->fresh()->shepherd->is($shepherd))->toBeTrue()
        ->and($member->fresh()->branchLeader->is($branchLeader))->toBeTrue()
        ->and($shepherd->fresh()->shepherdedMembers->contains($member))->toBeTrue()
        ->and($shepherd->fresh()->is_shepherd)->toBeTrue()
        ->and($shepherd->fresh()->shepherd_id)->toBe($branchLeaderMember->id);
});

it('assigns a designated shepherd to the active branch leader', function () {
    $branch = Branch::factory()->create();
    $leaderMember = Member::factory()->create();
    $shepherd = Member::factory()->create(['is_shepherd' => true]);
    foreach ([$leaderMember, $shepherd] as $branchMember) {
        MemberBranch::query()->create([
            'member_id' => $branchMember->id,
            'branch_id' => $branch->id,
            'joined_date' => today(),
            'is_primary' => true,
            'status' => MemberBranchStatus::Active,
        ]);
    }

    BranchLeader::query()->create([
        'branch_id' => $branch->id,
        'member_id' => $leaderMember->id,
        'leadership_title_id' => LeadershipTitle::query()->create(['name' => 'Shepherd Supervisor'])->id,
        'start_date' => today(),
        'is_active' => true,
    ]);

    expect($shepherd->fresh()->shepherd_id)->toBe($leaderMember->id);
});

it('uses the shepherds assigned branch leader when a branch has multiple leaders', function () {
    $branch = Branch::factory()->create();
    $firstLeaderMember = Member::factory()->create();
    $assignedLeaderMember = Member::factory()->create();
    foreach ([$firstLeaderMember, $assignedLeaderMember] as $leaderMember) {
        MemberBranch::query()->create([
            'member_id' => $leaderMember->id,
            'branch_id' => $branch->id,
            'joined_date' => today(),
            'is_primary' => true,
            'status' => MemberBranchStatus::Active,
        ]);
    }
    $title = LeadershipTitle::query()->create(['name' => 'Multiple Leader Supervisor']);
    BranchLeader::query()->create([
        'branch_id' => $branch->id,
        'member_id' => $firstLeaderMember->id,
        'leadership_title_id' => $title->id,
        'start_date' => today()->subYear(),
        'is_active' => true,
    ]);
    $assignedLeader = BranchLeader::query()->create([
        'branch_id' => $branch->id,
        'member_id' => $assignedLeaderMember->id,
        'leadership_title_id' => $title->id,
        'start_date' => today(),
        'is_active' => true,
    ]);
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');

    $response = $this->actingAs($administrator, 'backpack')->post(route('members.store'), [
        'primary_branch_id' => $branch->id,
        'branch_leader_id' => $assignedLeader->id,
        'is_shepherd' => true,
        'first_name' => 'Assigned',
        'last_name' => 'Shepherd',
        'membership_status' => MemberStatus::Member->value,
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();
    $shepherd = Member::query()->where('first_name', 'Assigned')->where('last_name', 'Shepherd')->firstOrFail();
    expect($shepherd->is_shepherd)->toBeTrue()
        ->and($shepherd->branch_leader_id)->toBe($assignedLeader->id)
        ->and($shepherd->shepherd_id)->toBe($assignedLeaderMember->id);
});

it('rejects a shepherd from another branch', function () {
    $memberBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $member = Member::factory()->create();
    $shepherd = Member::factory()->create();

    MemberBranch::query()->create([
        'member_id' => $member->id,
        'branch_id' => $memberBranch->id,
        'joined_date' => today(),
        'is_primary' => true,
        'status' => MemberBranchStatus::Active,
    ]);
    $branchLeaderMember = Member::factory()->create();
    MemberBranch::query()->create([
        'member_id' => $branchLeaderMember->id,
        'branch_id' => $memberBranch->id,
        'joined_date' => today(),
        'is_primary' => true,
        'status' => MemberBranchStatus::Active,
    ]);
    $branchLeader = BranchLeader::query()->create([
        'branch_id' => $memberBranch->id,
        'member_id' => $branchLeaderMember->id,
        'leadership_title_id' => LeadershipTitle::query()->create(['name' => 'Resident Pastor'])->id,
        'start_date' => today(),
        'is_active' => true,
    ]);
    MemberBranch::query()->create([
        'member_id' => $shepherd->id,
        'branch_id' => $otherBranch->id,
        'joined_date' => today(),
        'is_primary' => true,
        'status' => MemberBranchStatus::Active,
    ]);

    $admin = User::factory()->create();
    assignRole($admin, 'App Administrator');

    $this->actingAs($admin, 'backpack')->put(route('members.update', $member->id), [
        'shepherd_id' => $shepherd->id,
        'branch_leader_id' => $branchLeader->id,
        'first_name' => $member->first_name,
        'last_name' => $member->last_name,
        'membership_status' => MemberStatus::Member->value,
    ])->assertSessionHasErrors('shepherd_id');

    expect($member->fresh()->shepherd_id)->toBeNull();
});

it('requires a branch leader from the members primary branch', function () {
    $memberBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $leaderMember = Member::factory()->create();
    $title = LeadershipTitle::query()->create(['name' => 'District Pastor']);
    $otherBranchLeader = BranchLeader::query()->create([
        'branch_id' => $otherBranch->id,
        'member_id' => $leaderMember->id,
        'leadership_title_id' => $title->id,
        'start_date' => today(),
        'is_active' => true,
    ]);
    $admin = User::factory()->create();
    assignRole($admin, 'App Administrator');

    $response = $this->actingAs($admin, 'backpack')->post(route('members.store'), [
        'primary_branch_id' => $memberBranch->id,
        'branch_leader_id' => $otherBranchLeader->id,
        'first_name' => 'Abena',
        'last_name' => 'Owusu',
        'membership_status' => MemberStatus::Member->value,
    ]);

    $response->assertSessionHasErrors([
        'branch_leader_id' => 'Select an active branch leader from the member’s primary branch.',
    ]);
    $this->assertDatabaseMissing('members', ['first_name' => 'Abena', 'last_name' => 'Owusu']);
});

it('keeps the assigned branch leader separate from the shepherd', function () {
    $branch = Branch::factory()->create();
    $overseer = Member::factory()->create();
    MemberBranch::query()->create([
        'member_id' => $overseer->id,
        'branch_id' => $branch->id,
        'joined_date' => today(),
        'is_primary' => true,
        'status' => MemberBranchStatus::Active,
    ]);
    $branchLeader = BranchLeader::query()->create([
        'branch_id' => $branch->id,
        'member_id' => $overseer->id,
        'leadership_title_id' => LeadershipTitle::query()->create(['name' => 'Lead Pastor'])->id,
        'start_date' => today(),
        'is_active' => true,
    ]);
    $admin = User::factory()->create();
    assignRole($admin, 'App Administrator');

    $response = $this->actingAs($admin, 'backpack')->post(route('members.store'), [
        'primary_branch_id' => $branch->id,
        'branch_leader_id' => $branchLeader->id,
        'shepherd_id' => $overseer->id,
        'first_name' => 'Yaw',
        'last_name' => 'Boateng',
        'membership_status' => MemberStatus::Member->value,
    ]);

    $response->assertSessionHasErrors([
        'branch_leader_id' => 'The branch leader must be different from the shepherd.',
    ]);
    $this->assertDatabaseMissing('members', ['first_name' => 'Yaw', 'last_name' => 'Boateng']);
});
