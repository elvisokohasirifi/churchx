<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;
use App\PermissionCode;
use App\Services\BranchAccessService;

class MemberPolicy
{
    public function __construct(private BranchAccessService $access) {}

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, PermissionCode::MembersView)
            || $this->access->accessibleBranchIds($user, PermissionCode::MembersView)->isNotEmpty();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Member $member): bool
    {
        $branchId = $member->primaryBranchMembership()->value('branch_id');

        return $branchId !== null && $this->access->allows($user, PermissionCode::MembersView, $branchId);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->access->allows($user, PermissionCode::MembersCreate);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Member $member): bool
    {
        $branchId = $member->primaryBranchMembership()->value('branch_id');

        return $branchId !== null && $this->access->allows($user, PermissionCode::MembersUpdate, $branchId);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Member $member): bool
    {
        $branchId = $member->primaryBranchMembership()->value('branch_id');

        return $branchId !== null && $this->access->allows($user, PermissionCode::MembersDelete, $branchId);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Member $member): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Member $member): bool
    {
        return false;
    }
}
