<?php

namespace App\Services;

use App\MemberBranchStatus;
use App\MemberStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberBranch;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitorConversionService
{
    public function __construct(
        private MembershipNumberGenerator $numbers,
        private AuditLogService $audit,
    ) {}

    public function convert(Visitor $visitor, array $attributes = [], ?Branch $branch = null, ?User $actor = null): Member
    {
        return DB::transaction(function () use ($visitor, $attributes, $branch, $actor): Member {
            $lockedVisitor = Visitor::query()->lockForUpdate()->findOrFail($visitor->id);

            if ($lockedVisitor->converted_to_member_id !== null) {
                throw ValidationException::withMessages(['visitor' => 'This visitor has already been converted.']);
            }

            $targetBranch = $branch ?? $lockedVisitor->branch;
            $names = preg_split('/\s+/', trim($lockedVisitor->name)) ?: [];
            $member = Member::query()->create(array_merge([
                'membership_number' => $this->numbers->generate($targetBranch),
                'first_name' => array_shift($names) ?: $lockedVisitor->name,
                'last_name' => array_pop($names) ?: '-',
                'middle_name' => $names ? implode(' ', $names) : null,
                'phone' => $lockedVisitor->phone,
                'email' => $lockedVisitor->email,
                'address' => $lockedVisitor->address,
                'date_of_birth' => $lockedVisitor->date_of_birth,
                'gender' => $lockedVisitor->gender,
                'date_joined' => today(),
                'membership_status' => MemberStatus::NewConvert,
            ], Arr::only($attributes, [
                'first_name', 'middle_name', 'last_name', 'phone', 'email', 'address', 'date_of_birth', 'gender',
                'marital_status', 'occupation', 'highest_education', 'date_joined', 'membership_status', 'notes',
            ])));

            MemberBranch::query()->create([
                'member_id' => $member->id,
                'branch_id' => $targetBranch->id,
                'joined_date' => $member->date_joined ?? today(),
                'is_primary' => true,
                'status' => MemberBranchStatus::Active,
            ]);

            $lockedVisitor->update(['converted_to_member_id' => $member->id]);
            $this->audit->record('visitor.converted', $actor, $lockedVisitor, ['member_id' => $member->id]);

            return $member;
        });
    }
}
