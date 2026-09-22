<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Zone;
use App\PermissionCode;
use App\Services\BranchAccessService;

class ZonePolicy
{
    public function __construct(private BranchAccessService $access) {}

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, PermissionCode::BranchesView)
            || $this->access->accessibleBranchIds($user, PermissionCode::BranchesView)->isNotEmpty()
            || $this->access->activeZoneIds($user)->isNotEmpty();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Zone $zone): bool
    {
        if ($this->access->allows($user, PermissionCode::BranchesView)) {
            return true;
        }

        return $this->access->activeZoneIds($user)->contains($zone->id)
            || $zone->branches()->whereIn(
                'id',
                $this->access->accessibleBranchIds($user, PermissionCode::BranchesView),
            )->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->access->allows($user, PermissionCode::BranchesManage);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Zone $zone): bool
    {
        return $this->create($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Zone $zone): bool
    {
        return $this->create($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Zone $zone): bool
    {
        return $this->create($user);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Zone $zone): bool
    {
        return false;
    }
}
