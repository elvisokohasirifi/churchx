@extends(backpack_view('blank'))

@section('content')
<h2 class="mb-1">Bulk Assignments</h2>
<p class="text-muted mb-4">Select several records at once. Existing assignments are updated instead of duplicated.</p>

<div class="row g-4">
    @if($departmentBranches->isNotEmpty())
        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Departments to branch</h3></div>
            <form method="POST" action="{{ route('admin.bulk.branch-departments') }}">@csrf<div class="card-body">
                <label class="form-label">Branch</label><select class="form-select mb-3" name="branch_id" required>@foreach($departmentBranches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                <label class="form-label">Departments</label><select class="form-select" name="department_ids[]" multiple size="8" required>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</select>
            </div><div class="card-footer text-end"><button class="btn btn-primary">Assign departments</button></div></form>
        </div></div>

        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Members to department</h3></div>
            <form method="POST" action="{{ route('admin.bulk.department-members') }}">@csrf<div class="card-body">
                <label class="form-label">Branch department</label><select class="form-select mb-3 branch-filter-source" name="branch_department_id" required>@foreach($branchDepartments as $item)<option value="{{ $item->id }}" data-branch="{{ $item->branch_id }}">{{ $item->branch->name }} — {{ $item->department->name }}</option>@endforeach</select>
                <label class="form-label">Role</label><select class="form-select mb-3" name="department_role_id" required>@foreach($departmentRoles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select>
                <label class="form-label">Members</label><select class="form-select branch-member-select" name="member_ids[]" multiple size="8" required>@foreach($members as $member)<option value="{{ $member->id }}" data-branch="{{ $member->primaryBranchMembership?->branch_id }}">{{ $member->full_name }} — {{ $member->membership_number }}</option>@endforeach</select>
                <label class="form-label mt-3">Joined date</label><input class="form-control" type="date" name="joined_date" value="{{ today()->toDateString() }}">
            </div><div class="card-footer text-end"><button class="btn btn-primary">Assign members</button></div></form>
        </div></div>
    @endif

    @if($groups->isNotEmpty())
        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Members to cell / group</h3></div>
            <form method="POST" action="{{ route('admin.bulk.group-members') }}">@csrf<div class="card-body">
                <label class="form-label">Cell / group</label><select class="form-select mb-3 branch-filter-source" name="group_id" required>@foreach($groups as $group)<option value="{{ $group->id }}" data-branch="{{ $group->branch_id }}">{{ $group->branch?->name }} — {{ $group->name }}</option>@endforeach</select>
                <label class="form-label">Members</label><select class="form-select branch-member-select" name="member_ids[]" multiple size="8" required>@foreach($members as $member)<option value="{{ $member->id }}" data-branch="{{ $member->primaryBranchMembership?->branch_id }}">{{ $member->full_name }} — {{ $member->membership_number }}</option>@endforeach</select>
                <div class="row g-3 mt-1"><div class="col-md-6"><label class="form-label">Role</label><input class="form-control" name="role"></div><div class="col-md-6"><label class="form-label">Joined date</label><input class="form-control" type="date" name="joined_date" value="{{ today()->toDateString() }}"></div></div>
            </div><div class="card-footer text-end"><button class="btn btn-success">Assign members</button></div></form>
        </div></div>
    @endif

    @if($memberBranches->isNotEmpty() && $branchLeaders->isNotEmpty())
        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Members to branch leader</h3></div>
            <form method="POST" action="{{ route('admin.bulk.branch-leader-members') }}">@csrf<div class="card-body">
                <label class="form-label">Branch</label><select class="form-select mb-3 branch-filter-source" name="branch_id" required>@foreach($memberBranches as $branch)<option value="{{ $branch->id }}" data-branch="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                <label class="form-label">Branch leader</label><select class="form-select mb-3 branch-target-select" name="branch_leader_id" required>@foreach($branchLeaders as $leader)<option value="{{ $leader->id }}" data-branch="{{ $leader->branch_id }}">{{ $leader->member->full_name }} — {{ $leader->leadershipTitle->name }}</option>@endforeach</select>
                <div class="d-flex align-items-center justify-content-between"><label class="form-label mb-0">Members</label><button class="btn btn-sm btn-outline-secondary" type="button" data-clear-members>Clear selection</button></div>
                <select class="form-select branch-member-select mt-2" name="member_ids[]" multiple size="8" required>@foreach($members as $member)<option value="{{ $member->id }}" data-branch="{{ $member->primaryBranchMembership?->branch_id }}">{{ $member->full_name }} — {{ $member->membership_number }}</option>@endforeach</select>
            </div><div class="card-footer text-end"><button class="btn btn-primary">Assign branch leader</button></div></form>
        </div></div>
    @endif

    @if($memberBranches->isNotEmpty())
        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Members to shepherd</h3></div>
            <form method="POST" action="{{ route('admin.bulk.shepherd-members') }}">@csrf<div class="card-body">
                <label class="form-label">Branch</label><select class="form-select mb-3 branch-filter-source" name="branch_id" required>@foreach($memberBranches as $branch)<option value="{{ $branch->id }}" data-branch="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                <label class="form-label">Shepherd</label><select class="form-select mb-3 branch-target-select" name="shepherd_id"><option value="">Clear shepherd assignment</option>@foreach($members as $member)<option value="{{ $member->id }}" data-branch="{{ $member->primaryBranchMembership?->branch_id }}">{{ $member->full_name }} — {{ $member->membership_number }}</option>@endforeach</select>
                <div class="d-flex align-items-center justify-content-between"><label class="form-label mb-0">Members</label><button class="btn btn-sm btn-outline-secondary" type="button" data-clear-members>Clear selection</button></div>
                <select class="form-select branch-member-select mt-2" name="member_ids[]" multiple size="8" required>@foreach($members as $member)<option value="{{ $member->id }}" data-branch="{{ $member->primaryBranchMembership?->branch_id }}">{{ $member->full_name }} — {{ $member->membership_number }}</option>@endforeach</select>
            </div><div class="card-footer text-end"><button class="btn btn-info">Update shepherds</button></div></form>
        </div></div>
    @endif

    @if($households->isNotEmpty())
        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Members to household</h3></div>
            <form method="POST" action="{{ route('admin.bulk.household-members') }}">@csrf<div class="card-body">
                <label class="form-label">Household</label><select class="form-select mb-3" name="household_id" required>@foreach($households as $household)<option value="{{ $household->id }}">{{ $household->family_name }}</option>@endforeach</select>
                <label class="form-label">Relationship</label><select class="form-select mb-3" name="relationship_id" required>@foreach($relationships as $relationship)<option value="{{ $relationship->id }}">{{ $relationship->name }}</option>@endforeach</select>
                <label class="form-label">Members</label><select class="form-select" name="member_ids[]" multiple size="8" required>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->full_name }} — {{ $member->membership_number }}</option>@endforeach</select>
            </div><div class="card-footer text-end"><button class="btn btn-warning">Assign members</button></div></form>
        </div></div>
    @endif
</div>
@endsection

@push('after_scripts')
<script>
document.querySelectorAll('.branch-filter-source').forEach((source) => {
    const members = source.closest('form').querySelector('.branch-member-select');
    const targets = source.closest('form').querySelectorAll('.branch-target-select');
    const filter = () => {
        const branch = source.selectedOptions[0]?.dataset.branch;
        [...members.options].forEach((option) => {
            option.hidden = option.dataset.branch !== branch;
            if (option.hidden) option.selected = false;
        });
        targets.forEach((target) => {
            [...target.options].forEach((option) => {
                option.hidden = option.value !== '' && option.dataset.branch !== branch;
            });
            if (target.selectedOptions[0]?.hidden) {
                target.value = [...target.options].find((option) => !option.hidden)?.value || '';
            }
        });
    };
    source.addEventListener('change', filter);
    filter();
});
document.querySelectorAll('[data-clear-members]').forEach((button) => {
    button.addEventListener('click', () => {
        [...button.closest('form').querySelector('.branch-member-select').options].forEach((option) => option.selected = false);
    });
});
</script>
@endpush
