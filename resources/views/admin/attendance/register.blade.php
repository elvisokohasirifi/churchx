@extends(backpack_view('blank'))

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div><h2 class="mb-1">Attendance Register</h2><div class="text-muted">Mark every active member in a branch as present or absent.</div></div>
    <a class="btn btn-outline-primary" href="{{ route('admin.attendance.create') }}">Use summary capture</a>
</div>

<div class="card mb-4"><div class="card-body"><form method="GET" action="{{ route('admin.attendance.register') }}" class="row g-3 align-items-end">
    <div class="col-md-5"><label class="form-label">Branch</label><select class="form-select" name="branch_id" onchange="this.form.submit()">@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($branch->id === $branchId)>{{ $branch->name }}</option>@endforeach</select></div>
    <div class="col-md-5"><label class="form-label">Service</label><select class="form-select" name="service_id" required>@forelse($services as $service)<option value="{{ $service->id }}" @selected($service->id === $serviceId)>{{ $service->name }} — {{ $service->date->format('M j, Y') }}</option>@empty<option value="">No services available</option>@endforelse</select></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Load register</button></div>
</form></div></div>

@if($serviceId && $members->isNotEmpty())
<form method="POST" action="{{ route('admin.attendance.register.store') }}">@csrf<input type="hidden" name="branch_id" value="{{ $branchId }}"><input type="hidden" name="service_id" value="{{ $serviceId }}">
    <div class="card"><div class="card-header d-flex justify-content-between"><h3 class="card-title">Branch members</h3><div><button type="button" class="btn btn-sm btn-outline-success" id="mark-all-present">All present</button> <button type="button" class="btn btn-sm btn-outline-secondary" id="mark-all-absent">All absent</button></div></div>
        <div class="table-responsive"><table class="table table-vcenter card-table"><thead><tr><th>Member</th><th>Membership #</th><th class="text-center">Present</th><th class="text-center">Absent</th></tr></thead><tbody>
        @foreach($members as $member) @php($saved = $member->attendances->first()?->status ?? 'absent')
            <tr><td>{{ $member->full_name }}</td><td>{{ $member->membership_number }}</td><td class="text-center"><input class="form-check-input attendance-present" type="radio" name="attendance[{{ $member->id }}]" value="present" @checked($saved === 'present') required></td><td class="text-center"><input class="form-check-input attendance-absent" type="radio" name="attendance[{{ $member->id }}]" value="absent" @checked($saved !== 'present') required></td></tr>
        @endforeach
        </tbody></table></div><div class="card-footer text-end"><button class="btn btn-primary">Save attendance register</button></div>
    </div>
</form>
@elseif($serviceId)
<div class="alert alert-info">There are no active members in this branch.</div>
@endif
@endsection

@push('after_scripts')
<script>
document.getElementById('mark-all-present')?.addEventListener('click', () => document.querySelectorAll('.attendance-present').forEach((input) => input.checked = true));
document.getElementById('mark-all-absent')?.addEventListener('click', () => document.querySelectorAll('.attendance-absent').forEach((input) => input.checked = true));
</script>
@endpush
