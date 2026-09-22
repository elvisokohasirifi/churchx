<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Visitor;
use App\PermissionCode;
use App\Services\BranchAccessService;

class VisitorPolicy
{
    public function __construct(private BranchAccessService $access) {}

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, PermissionCode::VisitorsView)
            || $this->access->accessibleBranchIds($user, PermissionCode::VisitorsView)->isNotEmpty();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Visitor $visitor): bool
    {
        return $this->access->allows($user, PermissionCode::VisitorsView, $visitor->branch_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->access->allows($user, PermissionCode::VisitorsCreate);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Visitor $visitor): bool
    {
        return $this->access->allows($user, PermissionCode::VisitorsUpdate, $visitor->branch_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Visitor $visitor): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Visitor $visitor): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Visitor $visitor): bool
    {
        return false;
    }
}
