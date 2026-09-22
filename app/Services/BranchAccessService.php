<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use App\Models\ZoneLeader;
use App\PermissionCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BranchAccessService
{
    /** @var list<string> */
    private const ZONE_LEADER_VIEW_PERMISSIONS = [
        'branches.view',
        'members.view',
        'visitors.view',
        'attendance.view',
        'departments.view',
        'groups.view',
        'events.view',
        'broadcasts.view',
        'assets.view',
    ];

    public function allows(User $user, PermissionCode|string $permission, Branch|string|null $branch = null): bool
    {
        if (! $user->is_active) {
            return false;
        }

        $permissionCode = $permission instanceof PermissionCode ? $permission->value : $permission;
        $branchId = $branch instanceof Branch ? $branch->getKey() : $branch;

        $hasRoleAccess = $user->roleAssignments()
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

        if ($hasRoleAccess || $branchId === null || ! $this->isZoneLeaderPermission($permissionCode)) {
            return $hasRoleAccess;
        }

        return Branch::query()
            ->whereKey($branchId)
            ->whereHas('zone', fn (Builder $query): Builder => $query
                ->where('is_active', true)
                ->whereIn('id', $this->activeZoneIds($user)))
            ->exists();
    }

    /** @return Collection<int, string> */
    public function accessibleBranchIds(User $user, PermissionCode|string $permission): Collection
    {
        if (! $user->is_active) {
            return collect();
        }

        if ($this->allows($user, $permission)) {
            return Branch::query()->pluck('id');
        }

        $permissionCode = $permission instanceof PermissionCode ? $permission->value : $permission;

        $roleBranchIds = $user->roleAssignments()
            ->where('is_active', true)
            ->whereNotNull('branch_id')
            ->whereHas('role.permissions', fn (Builder $query) => $query->where('code', $permissionCode))
            ->pluck('branch_id')
            ->unique()
            ->values();

        if (! $this->isZoneLeaderPermission($permissionCode)) {
            return $roleBranchIds;
        }

        $zoneBranchIds = Branch::query()
            ->whereHas('zone', fn (Builder $query): Builder => $query
                ->where('is_active', true)
                ->whereIn('id', $this->activeZoneIds($user)))
            ->pluck('id');

        return $roleBranchIds->merge($zoneBranchIds)->unique()->values();
    }

    /** @return Collection<int, string> */
    public function activeZoneIds(User $user): Collection
    {
        if (! $user->is_active) {
            return collect();
        }

        return ZoneLeader::query()
            ->where('user_id', $user->id)
            ->whereHas('zone', fn (Builder $query): Builder => $query->where('is_active', true))
            ->where('is_active', true)
            ->whereDate('start_date', '<=', today())
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('end_date')
                ->orWhereDate('end_date', '>=', today()))
            ->pluck('zone_id')
            ->unique()
            ->values();
    }

    private function isZoneLeaderPermission(string $permissionCode): bool
    {
        return in_array($permissionCode, self::ZONE_LEADER_VIEW_PERMISSIONS, true);
    }
}
