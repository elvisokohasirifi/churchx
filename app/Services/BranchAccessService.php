<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use App\PermissionCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BranchAccessService
{
    public function allows(User $user, PermissionCode|string $permission, Branch|string|null $branch = null): bool
    {
        if (! $user->is_active) {
            return false;
        }

        $permissionCode = $permission instanceof PermissionCode ? $permission->value : $permission;
        $branchId = $branch instanceof Branch ? $branch->getKey() : $branch;

        return $user->roleAssignments()
            ->where('is_active', true)
            ->when(
                $branchId === null,
                fn (Builder $query) => $query->whereNull('branch_id'),
                fn (Builder $query) => $query->where(
                    fn (Builder $scope) => $scope->whereNull('branch_id')->orWhere('branch_id', $branchId),
                ),
            )
            ->whereHas('role.permissions', fn (Builder $query) => $query->where('code', $permissionCode))
            ->exists();
    }

    /** @return Collection<int, string> */
    public function accessibleBranchIds(User $user, PermissionCode|string $permission): Collection
    {
        if ($this->allows($user, $permission)) {
            return Branch::query()->pluck('id');
        }

        $permissionCode = $permission instanceof PermissionCode ? $permission->value : $permission;

        return $user->roleAssignments()
            ->where('is_active', true)
            ->whereNotNull('branch_id')
            ->whereHas('role.permissions', fn (Builder $query) => $query->where('code', $permissionCode))
            ->pluck('branch_id')
            ->unique()
            ->values();
    }
}
