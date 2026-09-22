<?php

use App\MemberBranchStatus;
use App\MemberStatus;
use App\Models\BranchLeader;
use App\Models\LeadershipTitle;
use App\Models\Member;
use App\Models\MemberBranch;
use App\Models\Visitor;
use App\Services\VisitorConversionService;
use Illuminate\Validation\ValidationException;

it('converts a visitor into a member and retains visitor history', function () {
    $visitor = Visitor::factory()->create(['name' => 'Ama Grace Mensah']);
    $leaderMember = Member::factory()->create();
    MemberBranch::query()->create(['member_id' => $leaderMember->id, 'branch_id' => $visitor->branch_id, 'joined_date' => today(), 'is_primary' => true, 'status' => MemberBranchStatus::Active]);
    $branchLeader = BranchLeader::query()->create(['branch_id' => $visitor->branch_id, 'member_id' => $leaderMember->id, 'leadership_title_id' => LeadershipTitle::query()->create(['name' => 'Visitor Pastor'])->id, 'start_date' => today(), 'is_active' => true]);

    $member = app(VisitorConversionService::class)->convert($visitor, [
        'branch_leader_id' => $branchLeader->id,
        'first_name' => 'Ama',
        'middle_name' => 'Grace',
        'last_name' => 'Mensah',
        'membership_status' => MemberStatus::NewConvert,
    ]);

    expect($visitor->fresh()->converted_to_member_id)->toBe($member->id)
        ->and($member->primaryBranchMembership->branch_id)->toBe($visitor->branch_id)
        ->and($member->branch_leader_id)->toBe($branchLeader->id)
        ->and($member->phone)->toBe($visitor->phone);
});

it('prevents a visitor from being converted twice', function () {
    $visitor = Visitor::factory()->create();
    $leaderMember = Member::factory()->create();
    MemberBranch::query()->create(['member_id' => $leaderMember->id, 'branch_id' => $visitor->branch_id, 'joined_date' => today(), 'is_primary' => true, 'status' => MemberBranchStatus::Active]);
    $branchLeader = BranchLeader::query()->create(['branch_id' => $visitor->branch_id, 'member_id' => $leaderMember->id, 'leadership_title_id' => LeadershipTitle::query()->create(['name' => 'Conversion Pastor'])->id, 'start_date' => today(), 'is_active' => true]);
    $service = app(VisitorConversionService::class);
    $service->convert($visitor, ['branch_leader_id' => $branchLeader->id]);

    expect(fn () => $service->convert($visitor->fresh(), ['branch_leader_id' => $branchLeader->id]))->toThrow(ValidationException::class);
});
