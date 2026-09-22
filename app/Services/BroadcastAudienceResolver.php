<?php

namespace App\Services;

use App\Models\Broadcast;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BroadcastAudienceResolver
{
    public function __construct(private readonly MemberAudienceFilter $memberAudienceFilter) {}

    /** @return Collection<int, Member> */
    public function resolve(Broadcast $broadcast): Collection
    {
        $members = collect();
        foreach ($broadcast->audiences as $audience) {
            $query = Member::query();
            match ($audience->audience_type) {
                'all_members' => $this->memberAudienceFilter->apply(
                    $query,
                    $audience->filter_field,
                    $audience->filter_operator,
                    $audience->filter_value,
                ),
                'member' => $query->whereKey($audience->audience_id),
                'branch' => $query->whereHas('branchHistory', fn (Builder $q) => $q->where('branch_id', $audience->audience_id)->where('is_primary', true)->whereNull('left_date')),
                'gender' => $query->where('gender', $audience->audience_id),
                'membership_status' => $query->where('membership_status', $audience->audience_id),
                'department' => $query->whereHas('departmentMemberships', fn (Builder $q) => $q
                    ->whereHas('branchDepartment', fn (Builder $d) => $d
                        ->where('department_id', $audience->audience_id)
                        ->where('is_active', true)
                        ->when($audience->branch_id, fn (Builder $branch) => $branch->where('branch_id', $audience->branch_id)))
                    ->where('is_active', true)),
                'group' => $query->whereHas('groupMemberships', fn (Builder $q) => $q
                    ->where('group_id', $audience->audience_id)
                    ->where('is_active', true)
                    ->when($audience->branch_id, fn (Builder $membership) => $membership
                        ->whereHas('group', fn (Builder $group) => $group->where('branch_id', $audience->branch_id)))),
                'role' => $query->whereHas('user.roleAssignments', fn (Builder $q) => $q->where('role_id', $audience->audience_id)->where('is_active', true)),
                default => $query->whereRaw('1 = 0'),
            };
            $members = $members->merge($query->get());
        }

        return $members->unique('id')->values();
    }
}
