@if ($crud->hasAccess('create'))
    <a href="{{ route('admin.branches.import.create') }}" class="btn btn-outline-primary" bp-button="branch-csv-import">
        <i class="la la-file-import" aria-hidden="true"></i>
        <span>Import CSV</span>
    </a>
    <a href="{{ route('admin.branches.import.sample') }}" class="btn btn-outline-secondary" bp-button="branch-csv-sample">
        <i class="la la-download" aria-hidden="true"></i>
        <span>Sample CSV</span>
    </a>
@endif
