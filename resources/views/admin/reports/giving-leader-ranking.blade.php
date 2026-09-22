@extends(backpack_view('blank'))

@section('content')
    <h2 class="mb-1">Giving Ranking by Branch Leader</h2>
    <p class="text-muted">Completed giving across all branches led by the same person is combined.</p>
    @include('admin.reports.partials.period-filters')
    <div class="card"><div class="table-responsive"><table class="table table-vcenter card-table">
        <thead><tr><th>Rank</th><th>Leader</th><th>Branches</th><th class="text-end">Services with giving</th><th class="text-end">Total giving</th></tr></thead>
        <tbody>@forelse ($rows as $row)
            <tr><td><span class="badge bg-{{ $loop->iteration <= 3 ? 'green' : 'secondary' }}-lt">#{{ $loop->iteration }}</span></td><td><strong>{{ $row['leader']?->full_name ?? 'Unknown leader' }}</strong></td><td>{{ $row['branches'] ?: '—' }}</td><td class="text-end">{{ number_format($row['services']) }}</td><td class="text-end fw-bold">{{ $currency }} {{ number_format($row['total'], 2) }}</td></tr>
        @empty <tr><td colspan="5" class="text-center text-muted py-5">No leader giving data is available for this period.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
