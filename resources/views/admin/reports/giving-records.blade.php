@extends(backpack_view('blank'))

@section('content')
    <h2 class="mb-1">Giving Records by Service</h2>
    <p class="text-muted">Completed income entries grouped by service for the selected period.</p>
    @include('admin.reports.partials.period-filters')
    <div class="card"><div class="table-responsive"><table class="table table-vcenter card-table">
        <thead><tr><th>Service</th><th>Date</th><th>Branches</th><th class="text-end">Entries</th><th class="text-end">Total giving</th></tr></thead>
        <tbody>@forelse ($rows as $row)
            <tr><td><strong>{{ $row['service']->name }}</strong></td><td>{{ $row['service']->date->format('M j, Y') }}</td><td>{{ $row['branches'] ?: '—' }}</td><td class="text-end">{{ number_format($row['entries']) }}</td><td class="text-end fw-bold">{{ $currency }} {{ number_format($row['giving'], 2) }}</td></tr>
        @empty <tr><td colspan="5" class="text-center text-muted py-5">No completed service income was recorded for this period.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
