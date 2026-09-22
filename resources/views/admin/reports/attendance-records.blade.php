@extends(backpack_view('blank'))

@section('content')
    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
        <div><h2 class="mb-1">Attendance Records</h2><p class="text-muted mb-0">Member attendance from {{ $from->format('M j, Y') }} to {{ $to->format('M j, Y') }}.</p></div>
        <span class="badge bg-blue-lt fs-5">{{ $rows->count() }} members</span>
    </div>
    @include('admin.reports.partials.period-filters', ['showSearch' => true])
    <div class="card"><div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>Member</th><th>Branch</th><th class="text-center">Present</th><th class="text-center">Absent</th><th class="text-center">Marked services</th><th>Attendance rate</th><th>Last present</th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td><strong>{{ $row['member']->full_name }}</strong><div class="small text-muted">{{ $row['member']->membership_number }}</div></td>
                    <td>{{ $row['branch']?->name ?? '—' }}</td>
                    <td class="text-center text-success fw-bold">{{ $row['present'] }}</td>
                    <td class="text-center text-danger fw-bold">{{ $row['absent'] }}</td>
                    <td class="text-center">{{ $row['marked'] }}</td>
                    <td style="min-width: 150px">
                        @if ($row['rate'] !== null)
                            <div class="d-flex justify-content-between small"><span>{{ number_format($row['rate'], 1) }}%</span></div>
                            <div class="progress progress-sm"><div class="progress-bar bg-success" style="width: {{ $row['rate'] }}%"></div></div>
                        @else <span class="text-muted">No records</span> @endif
                    </td>
                    <td>{{ $row['last_present_date'] ? \Illuminate\Support\Carbon::parse($row['last_present_date'])->format('M j, Y') : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-5">No members match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
@endsection
