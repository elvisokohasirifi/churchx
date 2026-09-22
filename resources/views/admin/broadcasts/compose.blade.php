@extends(backpack_view('blank'))

@section('content')
<div class="row g-3">
    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Compose Broadcast</h3></div>
            <form method="POST" action="{{ route('admin.broadcasts.store') }}">
                @csrf
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">Please correct the highlighted fields.</div>
                    @endif

                    <label class="form-label" for="title">Title</label>
                    <input id="title" class="form-control @error('title') is-invalid @enderror" name="title" value="{{ old('title') }}" required>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror

                    <label class="form-label mt-3" for="message">Message</label>
                    <textarea id="message" class="form-control @error('message') is-invalid @enderror" rows="6" name="message" required>{{ old('message') }}</textarea>
                    @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror

                    <div class="row g-3 mt-0">
                        <div class="col-md-6">
                            <label class="form-label" for="channel">Channel</label>
                            <select id="channel" class="form-select @error('channel') is-invalid @enderror" name="channel">
                                @foreach (['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'push' => 'Push'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('channel', 'email') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('channel')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="audience_type">Audience</label>
                            <select id="audience_type" class="form-select @error('audience_type') is-invalid @enderror" name="audience_type">
                                @foreach (['all_members' => 'All members', 'branch' => 'Branch', 'department' => 'Department', 'group' => 'Group'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('audience_type', 'all_members') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('audience_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div id="member-filter-fields" class="mt-3" hidden>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label mb-0">Member filter <span class="text-muted fw-normal">(optional)</span></label>
                            <small class="text-muted">Leave Field empty to include every member.</small>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label" for="filter_field">Field</label>
                                <select id="filter_field" class="form-select @error('filter_field') is-invalid @enderror" name="filter_field">
                                    <option value="">All member records</option>
                                    @foreach ($memberFields as $value => $label)
                                        <option value="{{ $value }}" @selected(old('filter_field') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('filter_field')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="filter_operator">Operator</label>
                                <select id="filter_operator" class="form-select @error('filter_operator') is-invalid @enderror" name="filter_operator">
                                    @foreach ($memberFilterOperators as $value => $label)
                                        <option value="{{ $value }}" @selected(old('filter_operator', '=') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('filter_operator')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="filter_value">Condition</label>
                                <input id="filter_value" class="form-control @error('filter_value') is-invalid @enderror" name="filter_value" value="{{ old('filter_value') }}" placeholder="Value">
                                @error('filter_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <small class="form-hint mt-2">Use commas for In/Not in and two comma-separated values for Between. Use % as the wildcard for Like.</small>
                    </div>

                    <div id="branch-field" class="mt-3" hidden>
                        <label class="form-label" for="branch_id">Branch</label>
                        <select id="branch_id" class="form-select @error('branch_id') is-invalid @enderror" name="branch_id">
                            <option value="">Choose a branch</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(old('branch_id') === $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div id="department-field" class="mt-3" hidden>
                        <label class="form-label" for="department_id">Department</label>
                        <select id="department_id" class="form-select @error('department_id') is-invalid @enderror" name="department_id">
                            <option value="">Choose a department</option>
                            @foreach ($branchDepartments as $branchDepartment)
                                <option value="{{ $branchDepartment->department_id }}" data-branch-id="{{ $branchDepartment->branch_id }}" @selected(old('department_id') === $branchDepartment->department_id)>{{ $branchDepartment->department->name }}</option>
                            @endforeach
                        </select>
                        @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div id="group-field" class="mt-3" hidden>
                        <label class="form-label" for="group_id">Group</label>
                        <select id="group_id" class="form-select @error('group_id') is-invalid @enderror" name="group_id">
                            <option value="">Choose a group</option>
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}" data-branch-id="{{ $group->branch_id }}" @selected(old('group_id') === $group->id)>{{ $group->name }}</option>
                            @endforeach
                        </select>
                        @error('group_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <label class="form-label mt-3" for="scheduled_at">Schedule</label>
                    <input id="scheduled_at" class="form-control @error('scheduled_at') is-invalid @enderror" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}">
                    @error('scheduled_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="card-footer text-end"><button class="btn btn-primary">Save broadcast</button></div>
            </form>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Title</th><th>Channel</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($broadcasts as $broadcast)
                        <tr>
                            <td>{{ $broadcast->title }}</td>
                            <td>{{ $broadcast->channel->value }}</td>
                            <td>{{ $broadcast->status->value }}</td>
                            <td>
                                @if (in_array($broadcast->status->value, ['draft', 'scheduled']))
                                    <form method="POST" action="{{ route('admin.broadcasts.send', $broadcast) }}">@csrf<button class="btn btn-sm btn-primary">Queue</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No broadcasts yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $broadcasts->links() }}
    </div>
</div>
@endsection

@push('after_scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const audienceType = document.getElementById('audience_type');
    const memberFilterFields = document.getElementById('member-filter-fields');
    const filterField = document.getElementById('filter_field');
    const filterOperator = document.getElementById('filter_operator');
    const filterValue = document.getElementById('filter_value');
    const branchField = document.getElementById('branch-field');
    const branch = document.getElementById('branch_id');
    const departmentField = document.getElementById('department-field');
    const department = document.getElementById('department_id');
    const groupField = document.getElementById('group-field');
    const group = document.getElementById('group_id');

    const filterOptions = (select) => {
        const branchId = branch.value;
        let selectedIsVisible = false;

        Array.from(select.options).forEach((option) => {
            if (!option.dataset.branchId) {
                option.hidden = false;
                return;
            }

            option.hidden = option.dataset.branchId !== branchId;
            selectedIsVisible ||= option.selected && !option.hidden;
        });

        if (!selectedIsVisible) {
            select.value = '';
        }
    };

    const updateAudienceFields = () => {
        const type = audienceType.value;
        const isAllMembers = type === 'all_members';
        const needsBranch = ['branch', 'department', 'group'].includes(type);
        const hasMemberFilter = isAllMembers && filterField.value !== '';
        const operatorNeedsValue = !['is_null', 'is_not_null'].includes(filterOperator.value);

        memberFilterFields.hidden = !isAllMembers;
        filterField.disabled = !isAllMembers;
        filterOperator.disabled = !hasMemberFilter;
        filterOperator.required = hasMemberFilter;
        filterValue.disabled = !hasMemberFilter || !operatorNeedsValue;
        filterValue.required = hasMemberFilter && operatorNeedsValue;
        branchField.hidden = !needsBranch;
        branch.disabled = !needsBranch;
        branch.required = needsBranch;
        departmentField.hidden = type !== 'department';
        department.disabled = type !== 'department';
        department.required = type === 'department';
        groupField.hidden = type !== 'group';
        group.disabled = type !== 'group';
        group.required = type === 'group';

        filterOptions(department);
        filterOptions(group);
    };

    audienceType.addEventListener('change', updateAudienceFields);
    filterField.addEventListener('change', updateAudienceFields);
    filterOperator.addEventListener('change', updateAudienceFields);
    branch.addEventListener('change', updateAudienceFields);
    updateAudienceFields();
});
</script>
@endpush
