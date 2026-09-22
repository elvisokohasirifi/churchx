@if ($entry->converted_to_member_id === null && backpack_user()?->can('update', $entry))
    <a href="{{ route('admin.visitors.convert.create', $entry) }}" class="btn btn-sm btn-link"><i class="la la-user-plus"></i> Convert</a>
@endif
