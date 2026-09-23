@extends(backpack_view('blank'))

@section('content')
    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h2 class="mb-1">Filter Members</h2>
            <p class="text-muted mb-0">Find members by branch and one field condition.</p>
        </div>
        <span class="badge bg-blue-lt fs-5">{{ number_format($members->total()) }} members</span>
    </div>

    <form method="GET" action="{{ route('admin.reports.member-filter') }}" class="card card-body mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="member-filter-search">Search members</label>
                <input
                    class="form-control @error('q') is-invalid @enderror"
                    id="member-filter-search"
                    type="search"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Name, membership number, phone or email"
                >
                @error('q') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="member-filter-branch">Branch</label>
                <select class="form-select @error('branch_id') is-invalid @enderror" id="member-filter-branch" name="branch_id">
                    <option value="">All accessible branches</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
                @error('branch_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4"></div>
            <div class="col-md-4">
                <label class="form-label" for="member-filter-field">Field</label>
                <select class="form-select @error('filter_field') is-invalid @enderror" id="member-filter-field" name="filter_field">
                    <option value="">Choose a member field</option>
                    @foreach ($fields as $value => $label)
                        <option value="{{ $value }}" @selected($filterField === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('filter_field') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="member-filter-operator">Operator</label>
                <select class="form-select @error('filter_operator') is-invalid @enderror" id="member-filter-operator" name="filter_operator">
                    <option value="">Choose an operator</option>
                    @foreach ($operators as $value => $label)
                        <option value="{{ $value }}" @selected($filterOperator === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('filter_operator') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label" for="member-filter-condition">Condition</label>
                <input
                    class="form-control @error('filter_condition') is-invalid @enderror"
                    id="member-filter-condition"
                    type="text"
                    name="filter_condition"
                    value="{{ $filterCondition }}"
                    placeholder="Value or comma-separated values"
                >
                @error('filter_condition') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit"><i class="la la-filter"></i> Apply</button>
                <a class="btn btn-outline-secondary" href="{{ route('admin.reports.member-filter') }}" title="Clear filters" aria-label="Clear filters"><i class="la la-times"></i></a>
            </div>
            <div class="col-12">
                <p class="form-hint mb-0">Use commas for In list and Between. For Like, use <code>%</code> as a wildcard, for example <code>%Grace%</code>. Empty/null operators do not require a condition.</p>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Branch</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Branch leader</th>
                        <th>Shepherd</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($members as $member)
                        <tr>
                            <td><strong>{{ $member->full_name }}</strong><div class="small text-muted">{{ $member->membership_number }}</div></td>
                            <td>{{ $member->primaryBranchMembership?->branch?->name ?? '—' }}</td>
                            <td>{{ $member->phone ?? '—' }}</td>
                            <td>{{ $member->email ?? '—' }}</td>
                            <td>{{ $member->branchLeader?->member?->full_name ?? '—' }}</td>
                            <td>{{ $member->shepherd?->full_name ?? '—' }}</td>
                            <td><span class="badge bg-secondary-lt">{{ str($member->membership_status->value)->headline() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">No members match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($members->hasPages())
            <div class="card-footer">{{ $members->links() }}</div>
        @endif
    </div>
@endsection
