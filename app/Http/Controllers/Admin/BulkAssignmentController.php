<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchDepartment;
use App\Models\BranchDepartmentMember;
use App\Models\BranchLeader;
use App\Models\ChurchGroup;
use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\GroupMember;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\HouseholdRelationship;
use App\Models\Member;
use App\PermissionCode;
use App\Services\BranchAccessService;
use App\Services\ShepherdHierarchyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BulkAssignmentController extends Controller
{
    public function index(BranchAccessService $access): View
    {
        $departmentBranchIds = $access->accessibleBranchIds(backpack_user(), PermissionCode::DepartmentsManage);
        $groupBranchIds = $access->accessibleBranchIds(backpack_user(), PermissionCode::GroupsManage);
        $memberBranchIds = $access->accessibleBranchIds(backpack_user(), PermissionCode::MembersUpdate);
        abort_unless($departmentBranchIds->isNotEmpty() || $groupBranchIds->isNotEmpty() || $memberBranchIds->isNotEmpty(), 403);

        return view('admin.bulk-assignments', [
            'departmentBranches' => Branch::query()->whereIn('id', $departmentBranchIds)->orderBy('name')->get(),
            'departments' => Department::query()->orderBy('name')->get(),
            'branchDepartments' => BranchDepartment::query()->with(['branch', 'department'])->whereIn('branch_id', $departmentBranchIds)->orderBy('branch_id')->get(),
            'departmentRoles' => DepartmentRole::query()->orderBy('name')->get(),
            'groups' => ChurchGroup::query()->with('branch')->whereIn('branch_id', $groupBranchIds)->orderBy('name')->get(),
            'households' => Household::query()->orderBy('family_name')->get(),
            'relationships' => HouseholdRelationship::query()->orderBy('name')->get(),
            'memberBranches' => Branch::query()->whereIn('id', $memberBranchIds)->orderBy('name')->get(),
            'branchLeaders' => BranchLeader::query()
                ->with(['branch:id,name', 'member:id,first_name,middle_name,last_name', 'leadershipTitle:id,name'])
                ->whereIn('branch_id', $memberBranchIds)
                ->where('is_active', true)
                ->whereDate('start_date', '<=', today())
                ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
                ->orderBy('start_date')
                ->orderBy('id')
                ->get(),
            'members' => Member::query()->with('primaryBranchMembership.branch')->whereHas('primaryBranchMembership', fn ($query) => $query->whereIn('branch_id', $departmentBranchIds->merge($groupBranchIds)->merge($memberBranchIds)->unique()))->orderBy('first_name')->get(),
        ]);
    }

    public function branchDepartments(Request $request, BranchAccessService $access): RedirectResponse
    {
        $branchIds = $access->accessibleBranchIds(backpack_user(), PermissionCode::DepartmentsManage);
        $data = $request->validate([
            'branch_id' => ['required', 'uuid', Rule::in($branchIds->all())],
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['required', 'uuid', 'distinct', 'exists:departments,id'],
        ]);

        DB::transaction(function () use ($data): void {
            foreach ($data['department_ids'] as $departmentId) {
                BranchDepartment::query()->updateOrCreate(
                    ['branch_id' => $data['branch_id'], 'department_id' => $departmentId],
                    ['is_active' => true],
                );
            }
        });

        return back()->with('success', 'Departments assigned to the branch.');
    }

    public function departmentMembers(Request $request, BranchAccessService $access): RedirectResponse
    {
        $data = $request->validate([
            'branch_department_id' => ['required', 'uuid', 'exists:branch_departments,id'],
            'department_role_id' => ['required', 'uuid', 'exists:department_roles,id'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['required', 'uuid', 'distinct', 'exists:members,id'],
            'joined_date' => ['nullable', 'date'],
        ]);
        $branchDepartment = BranchDepartment::query()->findOrFail($data['branch_department_id']);
        abort_unless($access->allows(backpack_user(), PermissionCode::DepartmentsManage, $branchDepartment->branch_id), 403);
        $this->ensureMembersBelongToBranch($data['member_ids'], $branchDepartment->branch_id);

        DB::transaction(function () use ($data): void {
            foreach ($data['member_ids'] as $memberId) {
                BranchDepartmentMember::query()->updateOrCreate(
                    ['branch_department_id' => $data['branch_department_id'], 'member_id' => $memberId],
                    ['department_role_id' => $data['department_role_id'], 'joined_date' => $data['joined_date'] ?? today(), 'left_date' => null, 'is_active' => true],
                );
            }
        });

        return back()->with('success', 'Members assigned to the department.');
    }

    public function groupMembers(Request $request, BranchAccessService $access): RedirectResponse
    {
        $data = $request->validate([
            'group_id' => ['required', 'uuid', 'exists:church_groups,id'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['required', 'uuid', 'distinct', 'exists:members,id'],
            'role' => ['nullable', 'string', 'max:255'],
            'joined_date' => ['nullable', 'date'],
        ]);
        $group = ChurchGroup::query()->findOrFail($data['group_id']);
        abort_unless($group->branch_id !== null && $access->allows(backpack_user(), PermissionCode::GroupsManage, $group->branch_id), 403);
        $this->ensureMembersBelongToBranch($data['member_ids'], $group->branch_id);

        DB::transaction(function () use ($data): void {
            foreach ($data['member_ids'] as $memberId) {
                GroupMember::query()->updateOrCreate(
                    ['group_id' => $data['group_id'], 'member_id' => $memberId],
                    ['role' => $data['role'] ?? null, 'joined_date' => $data['joined_date'] ?? today(), 'left_date' => null, 'is_active' => true],
                );
            }
        });

        return back()->with('success', 'Members assigned to the group.');
    }

    public function householdMembers(Request $request, BranchAccessService $access): RedirectResponse
    {
        $branchIds = $access->accessibleBranchIds(backpack_user(), PermissionCode::MembersUpdate);
        $data = $request->validate([
            'household_id' => ['required', 'uuid', 'exists:households,id'],
            'relationship_id' => ['required', 'uuid', 'exists:household_relationships,id'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['required', 'uuid', 'distinct', 'exists:members,id'],
        ]);
        $allowedMembers = Member::query()->whereIn('id', $data['member_ids'])->whereHas('primaryBranchMembership', fn ($query) => $query->whereIn('branch_id', $branchIds))->count();
        abort_unless($allowedMembers === count($data['member_ids']), 403);

        DB::transaction(function () use ($data): void {
            foreach ($data['member_ids'] as $memberId) {
                HouseholdMember::query()->updateOrCreate(
                    ['household_id' => $data['household_id'], 'member_id' => $memberId],
                    ['relationship_id' => $data['relationship_id'], 'is_head' => false],
                );
            }
        });

        return back()->with('success', 'Members assigned to the household.');
    }

    public function branchLeaderMembers(Request $request, BranchAccessService $access, ShepherdHierarchyService $hierarchy): RedirectResponse
    {
        $branchIds = $access->accessibleBranchIds(backpack_user(), PermissionCode::MembersUpdate);
        $data = $request->validate([
            'branch_id' => ['required', 'uuid', Rule::in($branchIds->all())],
            'branch_leader_id' => ['required', 'uuid', 'exists:branch_leaders,id'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['required', 'uuid', 'distinct', 'exists:members,id'],
        ]);
        $branchLeader = BranchLeader::query()
            ->whereKey($data['branch_leader_id'])
            ->where('branch_id', $data['branch_id'])
            ->where('is_active', true)
            ->whereDate('start_date', '<=', today())
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
            ->first();
        abort_unless($branchLeader !== null, 422, 'Select an active leader from the selected branch.');
        $this->ensureMembersBelongToBranch($data['member_ids'], $data['branch_id']);

        $hasLeaderAsShepherd = Member::query()
            ->whereIn('id', $data['member_ids'])
            ->where('is_shepherd', false)
            ->where('shepherd_id', $branchLeader->member_id)
            ->exists();
        abort_if($hasLeaderAsShepherd, 422, 'The branch leader must be different from each selected member’s shepherd.');

        Member::query()->whereIn('id', $data['member_ids'])->update(['branch_leader_id' => $branchLeader->id]);
        $hierarchy->syncBranch($data['branch_id']);

        return back()->with('success', 'Branch leader assigned to the selected members.');
    }

    public function shepherdMembers(Request $request, BranchAccessService $access, ShepherdHierarchyService $hierarchy): RedirectResponse
    {
        $branchIds = $access->accessibleBranchIds(backpack_user(), PermissionCode::MembersUpdate);
        $data = $request->validate([
            'branch_id' => ['required', 'uuid', Rule::in($branchIds->all())],
            'shepherd_id' => ['nullable', 'uuid', 'exists:members,id'],
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['required', 'uuid', 'distinct', 'exists:members,id'],
        ]);
        $this->ensureMembersBelongToBranch($data['member_ids'], $data['branch_id']);
        $shepherdId = $data['shepherd_id'] ?? null;

        if ($shepherdId !== null) {
            $shepherdBelongsToBranch = Member::query()->whereKey($shepherdId)->whereHas(
                'primaryBranchMembership',
                fn ($query) => $query->where('branch_id', $data['branch_id']),
            )->exists();
            abort_unless($shepherdBelongsToBranch, 422, 'The shepherd must belong to the selected branch.');
            abort_if(in_array($shepherdId, $data['member_ids'], true), 422, 'A member cannot be their own shepherd.');

            $conflictsWithLeader = Member::query()
                ->whereIn('id', $data['member_ids'])
                ->where('is_shepherd', false)
                ->whereHas('branchLeader', fn ($query) => $query->where('member_id', $shepherdId))
                ->exists();
            abort_if($conflictsWithLeader, 422, 'The shepherd must be different from each selected member’s branch leader.');
        }

        Member::query()->whereIn('id', $data['member_ids'])->update(['shepherd_id' => $shepherdId]);

        if ($shepherdId !== null) {
            $hierarchy->markAndSync(Member::query()->findOrFail($shepherdId));
        }

        return back()->with('success', $shepherdId === null
            ? 'Shepherd assignments cleared for the selected members.'
            : 'Shepherd assigned to the selected members.');
    }

    /** @param list<string> $memberIds */
    private function ensureMembersBelongToBranch(array $memberIds, string $branchId): void
    {
        $count = Member::query()->whereIn('id', $memberIds)->whereHas(
            'primaryBranchMembership',
            fn ($query) => $query->where('branch_id', $branchId),
        )->count();

        abort_unless($count === count($memberIds), 422, 'Every selected member must belong to the selected branch.');
    }
}
