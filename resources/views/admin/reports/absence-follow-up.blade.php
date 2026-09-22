@extends(backpack_view('blank'))

@section('content')
    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
        <div><h2 class="mb-1">Absence Follow-up</h2><p class="text-muted mb-0">Members marked absent at least {{ $minimumAbsences }} times during the selected period.</p></div>
        <span class="badge bg-red-lt fs-5">{{ $rows->count() }} to follow up</span>
    </div>
    @include('admin.reports.partials.period-filters', ['showSearch' => true, 'showMinimumAbsences' => true])
    <div class="card"><div class="table-responsive"><table class="table table-vcenter card-table">
        <thead><tr><th>Member</th><th>Branch</th><th>Branch leader</th><th>Shepherd</th><th class="text-center">Absences</th><th>Last present</th><th>Contact</th></tr></thead>
        <tbody>@forelse ($rows as $row)
            <tr>
                <td><strong>{{ $row['member']->full_name }}</strong><div class="small text-muted">{{ $row['member']->membership_number }}</div></td>
                <td>{{ $row['branch']?->name ?? '—' }}</td>
                <td>{{ $row['member']->branchLeader?->member?->full_name ?? '—' }}</td>
                <td>{{ $row['member']->shepherd?->full_name ?? '—' }}</td>
                <td class="text-center"><span class="badge bg-red-lt">{{ $row['absent'] }}</span></td>
                <td>{{ $row['last_present_date'] ? \Illuminate\Support\Carbon::parse($row['last_present_date'])->format('M j, Y') : '—' }}</td>
                <td>@if ($row['member']->phone)<a class="btn btn-sm btn-outline-primary" href="tel:{{ $row['member']->phone }}"><i class="la la-phone"></i> {{ $row['member']->phone }}</a>@else <span class="text-muted">No phone</span>@endif</td>
            </tr>
        @empty <tr><td colspan="7" class="text-center text-muted py-5">No members meet the follow-up threshold.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
