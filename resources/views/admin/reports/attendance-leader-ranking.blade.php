@extends(backpack_view('blank'))

@section('content')
    <h2 class="mb-1">Attendance Ranking by Branch Leader</h2>
    <p class="text-muted">All branches led by the same person are combined. Rankings use captured attendance summaries.</p>
    @include('admin.reports.partials.period-filters')
    <div class="card"><div class="table-responsive"><table class="table table-vcenter card-table">
        <thead><tr><th>Rank</th><th>Leader</th><th>Branches</th><th class="text-end">Services</th><th class="text-end">Total attendance</th><th class="text-end">Average / service</th></tr></thead>
        <tbody>@forelse ($rows as $row)
            <tr><td><span class="badge bg-{{ $loop->iteration <= 3 ? 'yellow' : 'secondary' }}-lt">#{{ $loop->iteration }}</span></td><td><strong>{{ $row['leader']?->full_name ?? 'Unknown leader' }}</strong></td><td>{{ $row['branches'] ?: '—' }}</td><td class="text-end">{{ number_format($row['services']) }}</td><td class="text-end fw-bold">{{ number_format($row['total']) }}</td><td class="text-end">{{ number_format($row['average'], 1) }}</td></tr>
        @empty <tr><td colspan="6" class="text-center text-muted py-5">No branch leader attendance data is available for this period.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
