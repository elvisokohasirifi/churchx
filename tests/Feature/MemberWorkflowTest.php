<?php

use App\MemberBranchStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberBranch;
use App\Services\MembershipNumberGenerator;
use App\Services\MemberTransferService;
use Illuminate\Database\UniqueConstraintViolationException;

it('generates sequential human-readable membership numbers per branch', function () {
    $branch = Branch::factory()->create(['code' => 'ACC']);
    $generator = app(MembershipNumberGenerator::class);

    expect($generator->generate($branch))->toBe('MEM-ACC-000001')
        ->and($generator->generate($branch))->toBe('MEM-ACC-000002');
});

it('transfers a member while preserving branch history', function () {
    $oldBranch = Branch::factory()->create();
    $newBranch = Branch::factory()->create();
    $member = Member::factory()->create();
    $original = MemberBranch::query()->create([
        'member_id' => $member->id,
        'branch_id' => $oldBranch->id,
        'joined_date' => '2025-01-01',
        'is_primary' => true,
        'status' => MemberBranchStatus::Active,
    ]);

    $newMembership = app(MemberTransferService::class)->transfer($member, $newBranch, today());

    expect($original->fresh()->is_primary)->toBeFalse()
        ->and($original->fresh()->left_date->isToday())->toBeTrue()
        ->and($newMembership->branch_id)->toBe($newBranch->id)
        ->and($newMembership->is_primary)->toBeTrue()
        ->and($member->branchHistory()->count())->toBe(2);
});

it('enforces one active primary branch membership at the database boundary', function () {
    $member = Member::factory()->create();
    MemberBranch::query()->create([
        'member_id' => $member->id,
        'branch_id' => Branch::factory()->create()->id,
        'joined_date' => today(),
        'is_primary' => true,
        'status' => MemberBranchStatus::Active,
    ]);

    expect(fn () => MemberBranch::query()->create([
        'member_id' => $member->id,
        'branch_id' => Branch::factory()->create()->id,
        'joined_date' => today(),
        'is_primary' => true,
        'status' => MemberBranchStatus::Active,
    ]))->toThrow(UniqueConstraintViolationException::class);
});
