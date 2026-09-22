@if ($crud->hasAccess('create'))
    @php($resource = \Illuminate\Support\Str::afterLast($crud->route, '/'))
    <a href="{{ route('admin.setup.bulk.create', ['resource' => $resource]) }}" class="btn btn-outline-primary" bp-button="bulk-create">
        <i class="la la-layer-group" aria-hidden="true"></i>
        <span>Bulk add</span>
    </a>
@endif
