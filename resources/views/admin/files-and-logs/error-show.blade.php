@extends(backpack_view('blank'))

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div><h2 class="mb-1">{{ $log }}</h2><p class="text-muted mb-0">Showing up to the final 1 MB of this log.</p></div>
    <div><a class="btn btn-outline-secondary" href="{{ route('admin.error-logs.index') }}">Back</a> <a class="btn btn-primary" href="{{ route('admin.error-logs.download', $log) }}">Download</a></div>
</div>
<div class="card"><div class="card-body"><pre class="bg-dark text-light rounded p-3 mb-0 overflow-auto" style="max-height: 70vh; white-space: pre-wrap">{{ $contents }}</pre></div></div>
@endsection
