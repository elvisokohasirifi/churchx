<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\PermissionCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RoleProvisioningService
{
    /** @var list<string> */
    private const FINANCE_PERMISSIONS = [
        'income.view',
        'income.create',
        'income.update',
        'offerings.capture',
        'offerings.verify',
        'expenses.view',
        'expenses.create',
        'expenses.approve',
        'expenses.disburse',
        'transfers.create',
        'transfers.approve',
        'financial_reports.view',
    ];

    /** @return Collection<string, Role> */
    public function provision(): Collection
    {
        $permissions = collect(PermissionCode::cases())->mapWithKeys(function (PermissionCode $permission): array {
            $model = Permission::query()->updateOrCreate(
                ['code' => $permission->value],
                ['name' => Str::headline(str_replace('.', ' ', $permission->value))],
            );

            return [$permission->value => $model];
        });

        return collect($this->rolePermissions())->mapWithKeys(function (array $codes, string $roleName) use ($permissions): array {
            $role = Role::query()->updateOrCreate(
                ['name' => $roleName],
                ['description' => 'System role for '.$roleName.'.'],
            );
            $role->permissions()->sync($permissions->only($codes)->pluck('id'));

            return [$roleName => $role];
        });
    }

    /** @return array<string, list<string>> */
    private function rolePermissions(): array
    {
        return [
            'Church Overseer' => [
                'members.view', 'visitors.view', 'branches.view', 'attendance.view', 'departments.view',
                'groups.view', 'events.view', 'broadcasts.view', 'assets.view', 'users.view',
            ],
            'Church Leader' => [
                'members.view', 'visitors.view', 'branches.view', 'attendance.view', 'attendance.capture',
                'departments.view', 'groups.view', 'events.view', 'events.manage', 'broadcasts.view',
                'income.view', 'income.create',
            ],
            'Zone Leader' => [],
            'Finance Officer' => [
                'income.view', 'income.create', 'income.update', 'offerings.capture', 'offerings.verify',
                'expenses.view', 'expenses.create', 'expenses.approve', 'expenses.disburse', 'transfers.create',
                'transfers.approve', 'financial_reports.view',
            ],
            'Church Administrator' => array_values(array_filter(
                array_column(PermissionCode::cases(), 'value'),
                fn (string $code): bool => ! in_array($code, self::FINANCE_PERMISSIONS, true),
            )),
            'Branch Administrator' => [
                'members.view', 'members.create', 'members.update', 'visitors.view', 'visitors.create',
                'visitors.update', 'branches.view', 'attendance.view', 'attendance.capture', 'departments.view',
                'departments.manage', 'groups.view', 'groups.manage', 'events.view', 'events.manage',
                'broadcasts.view', 'income.view', 'income.create',
            ],
            'Department Leader' => ['members.view', 'departments.view', 'departments.manage'],
            'App Administrator' => array_column(PermissionCode::cases(), 'value'),
        ];
    }
}
