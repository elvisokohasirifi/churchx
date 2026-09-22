@extends(backpack_view('blank'))

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div><h2 class="mb-1">Backups</h2><p class="text-muted mb-0">Download existing application backups or queue a new one.</p></div>
    <form method="POST" action="{{ route('admin.backups.store') }}">@csrf<button class="btn btn-primary"><i class="la la-plus me-1"></i>Create backup</button></form>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>Backup</th><th>Disk</th><th>Size</th><th>Created</th><th class="text-end">Action</th></tr></thead>
            <tbody>
                @forelse ($backups as $backup)
                    <tr>
                        <td>{{ $backup['name'] }}</td><td>{{ $backup['disk'] }}</td><td>{{ number_format($backup['size'] / 1048576, 2) }} MB</td><td>{{ date('Y-m-d H:i:s', $backup['modified_at']) }}</td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.backups.download', ['disk' => $backup['disk'], 'path' => $backup['path']]) }}">Download</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-5">No backups have been created yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
