<?php

namespace App\Services;

use App\MemberBranchStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberBranch;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberTransferService
{
    public function __construct(private AuditLogService $audit) {}

    public function transfer(Member $member, Branch $branch, CarbonInterface $date, ?User $actor = null): MemberBranch
    {
        return DB::transaction(function () use ($member, $branch, $date, $actor): MemberBranch {
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

            $this->audit->record(
                'member.branch.transferred',
                $actor,
                $lockedMember,
                ['from_branch_id' => $current?->branch_id, 'to_branch_id' => $branch->id, 'date' => $date->toDateString()],
            );

            return $membership;
        });
    }
}
