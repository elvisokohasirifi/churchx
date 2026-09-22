@extends(backpack_view('blank'))

@section('content')
<div class="mb-4"><h2 class="mb-1">File System</h2><p class="text-muted mb-0">Read-only access to approved Laravel storage disks.</p></div>
<form method="GET" class="row g-2 mb-3">
    <div class="col-md-4"><select name="disk" class="form-select" onchange="this.form.submit()">@foreach ($disks as $availableDisk)<option value="{{ $availableDisk }}" @selected($availableDisk === $disk)>{{ ucfirst($availableDisk) }}</option>@endforeach</select></div>
</form>
<div class="card">
    <div class="card-header"><strong>{{ $disk }}:/{{ $path }}</strong></div>
    <div class="list-group list-group-flush">
        @if ($path !== '')
            <a class="list-group-item list-group-item-action" href="{{ route('admin.file-system.index', ['disk' => $disk, 'path' => $parent]) }}"><i class="la la-level-up-alt me-2"></i>Parent directory</a>
        @endif
        @foreach ($directories as $directory)
            <a class="list-group-item list-group-item-action" href="{{ route('admin.file-system.index', ['disk' => $disk, 'path' => $directory]) }}"><i class="la la-folder text-warning me-2"></i>{{ basename($directory) }}</a>
        @endforeach
        @foreach ($files as $file)
            <div class="list-group-item d-flex align-items-center justify-content-between">
                <span><i class="la la-file me-2"></i>{{ $file['name'] }} <small class="text-muted ms-2">{{ number_format($file['size'] / 1024, 1) }} KB · {{ date('Y-m-d H:i:s', $file['modified_at']) }}</small></span>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.file-system.download', ['disk' => $disk, 'path' => $file['path']]) }}">Download</a>
            </div>
        @endforeach
        @if ($directories->isEmpty() && $files->isEmpty())<div class="list-group-item text-center text-muted py-5">This directory is empty.</div>@endif
    </div>
</div>
@endsection
