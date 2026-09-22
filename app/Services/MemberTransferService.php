<?php

namespace App\Services;

use App\MemberBranchStatus;
use App\Models\Branch;
use App\Models\BranchLeader;
use App\Models\Member;
use App\Models\MemberBranch;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberTransferService
{
    public function __construct(private AuditLogService $audit) {}

    public function transfer(Member $member, Branch $branch, BranchLeader $branchLeader, CarbonInterface $date, ?User $actor = null): MemberBranch
    {
        return DB::transaction(function () use ($member, $branch, $branchLeader, $date, $actor): MemberBranch {
            $isValidLeader = $branchLeader->branch_id === $branch->id
                && $branchLeader->is_active
                && $branchLeader->start_date->lte($date)
                && ($branchLeader->end_date === null || $branchLeader->end_date->gte($date));

            if (! $isValidLeader) {
                throw ValidationException::withMessages([
                    'branch_leader_id' => 'Select an active leader from the destination branch.',
                ]);
            }

            $lockedMember = Member::query()->lockForUpdate()->findOrFail($member->id);
            $current = $lockedMember->branchHistory()
                ->where('is_primary', true)
                ->whereNull('left_date')
                ->lockForUpdate()
                ->first();

            if ($current?->branch_id === $branch->id) {
                throw ValidationException::withMessages(['branch_id' => 'The member already belongs to this branch.']);
            }

            $current?->update([
                'left_date' => $date->toDateString(),
                'is_primary' => false,
                'status' => MemberBranchStatus::Transferred,
            ]);

            $membership = MemberBranch::query()->create([
                'member_id' => $lockedMember->id,
                'branch_id' => $branch->id,
                'joined_date' => $date->toDateString(),
                'is_primary' => true,
                'status' => MemberBranchStatus::Active,
            ]);
            $shepherdRemainsInBranch = $lockedMember->shepherd_id !== null && Member::query()
                ->whereKey($lockedMember->shepherd_id)
                ->whereHas('primaryBranchMembership', fn ($query) => $query->where('branch_id', $branch->id))
                ->exists();
            $lockedMember->update([
                'branch_leader_id' => $branchLeader->id,
                'shepherd_id' => $shepherdRemainsInBranch && $lockedMember->shepherd_id !== $branchLeader->member_id
                    ? $lockedMember->shepherd_id
                    : null,
            ]);

            $this->audit->record(
                'member.branch.transferred',
                $actor,
                $lockedMember,
                [
                    'from_branch_id' => $current?->branch_id,
                    'to_branch_id' => $branch->id,
                    'branch_leader_id' => $branchLeader->id,
                    'date' => $date->toDateString(),
                ],
            );

            return $membership;
        });
    }
}
