@extends(backpack_view('blank'))

@section('content')
<div class="mb-4"><h2 class="mb-1">Error Logs</h2><p class="text-muted mb-0">Laravel log files from <code>storage/logs</code>.</p></div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>File</th><th>Size</th><th>Last modified</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td><i class="la la-file-alt me-2"></i>{{ $log['name'] }}</td>
                        <td>{{ number_format($log['size'] / 1024, 1) }} KB</td>
                        <td>{{ $log['modified_at']->setTimezone(new DateTimeZone(config('app.timezone')))->format('Y-m-d H:i:s') }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-primary" href="{{ route('admin.error-logs.show', $log['name']) }}">View</a>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.error-logs.download', $log['name']) }}">Download</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-5">No Laravel log files were found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
