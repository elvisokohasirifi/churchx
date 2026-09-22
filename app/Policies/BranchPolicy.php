<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\PermissionCode;
use App\Services\BranchAccessService;

class BranchPolicy
{
    public function __construct(private BranchAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, PermissionCode::BranchesView)
            || $this->access->accessibleBranchIds($user, PermissionCode::BranchesView)->isNotEmpty();
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->access->allows($user, PermissionCode::BranchesView, $branch);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, PermissionCode::BranchesManage);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->access->allows($user, PermissionCode::BranchesManage, $branch);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $this->update($user, $branch);
    }

    public function restore(User $user, Branch $branch): bool
    {
        return $this->update($user, $branch);
    }

    public function forceDelete(User $user, Branch $branch): bool
    {
        return false;
    }
}
