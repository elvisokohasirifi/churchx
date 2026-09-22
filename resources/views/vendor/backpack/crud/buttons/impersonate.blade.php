@if($entry->is_active && !$entry->is(backpack_user()) && $entry->roleAssignments()->where('is_active', true)->exists())
<form method="POST" action="{{ route('admin.users.impersonate', $entry) }}" class="d-inline" onsubmit="return confirm('Switch view to {{ addslashes($entry->name) }}?')">
    @csrf
    <button type="submit" class="btn btn-sm btn-link"><i class="la la-user-secret"></i> Impersonate</button>
</form>
@endif
