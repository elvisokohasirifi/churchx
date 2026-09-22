@if (backpack_user()?->can('update', $entry))
    <a href="{{ route('admin.members.transfer.create', $entry) }}" class="btn btn-sm btn-link"><i class="la la-exchange-alt"></i> Transfer</a>
@endif
