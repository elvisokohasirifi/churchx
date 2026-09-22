@extends(backpack_view('blank'))

@section('content')
    <h2 class="mb-1">Attendance Ranking by Church</h2>
    <p class="text-muted">Each branch or congregation is shown as a church location and ranked using captured attendance summaries.</p>
    @include('admin.reports.partials.period-filters')
    <div class="card"><div class="table-responsive"><table class="table table-vcenter card-table">
        <thead><tr><th>Rank</th><th>Church / Branch</th><th class="text-end">Services</th><th class="text-end">Total attendance</th><th class="text-end">Average / service</th></tr></thead>
        <tbody>@forelse ($rows as $row)
            <tr><td><span class="badge bg-{{ $loop->iteration <= 3 ? 'azure' : 'secondary' }}-lt">#{{ $loop->iteration }}</span></td><td><strong>{{ $row['branch']->name }}</strong></td><td class="text-end">{{ number_format($row['services']) }}</td><td class="text-end fw-bold">{{ number_format($row['attendance']) }}</td><td class="text-end">{{ number_format($row['average'], 1) }}</td></tr>
        @empty <tr><td colspan="5" class="text-center text-muted py-5">No accessible churches are available.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
