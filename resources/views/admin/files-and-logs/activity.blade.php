@extends(backpack_view('blank'))

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h2 class="mb-1">Activity Log</h2>
        <p class="text-muted mb-0">Model changes recorded across the application.</p>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>When</th><th>Event</th><th>User</th><th>Subject</th><th>Changes</th></tr></thead>
            <tbody>
                @forelse ($activities as $activity)
                    <tr>
                        <td class="text-nowrap">{{ $activity->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td><span class="badge bg-blue-lt">{{ ucfirst($activity->event ?? $activity->description) }}</span></td>
                        <td>{{ $activity->causer?->name ?? 'System' }}</td>
                        <td>
                            {{ $activity->subject ? class_basename($activity->subject) : class_basename($activity->subject_type ?? 'System') }}
                            @if ($activity->subject_id)<small class="d-block text-muted">{{ $activity->subject_id }}</small>@endif
                        </td>
                        <td>
                            @if ($activity->attribute_changes?->isNotEmpty())
                                <details><summary>View details</summary><pre class="bg-dark text-light rounded p-3 mt-2 mb-0">{{ json_encode($activity->attribute_changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
                            @elseif ($activity->properties?->isNotEmpty())
                                <details><summary>View details</summary><pre class="bg-dark text-light rounded p-3 mt-2 mb-0">{{ json_encode($activity->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
                            @else
                                <span class="text-muted">No field changes</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-5">No activity has been recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($activities->hasPages())<div class="card-footer">{{ $activities->links() }}</div>@endif
</div>
@endsection
