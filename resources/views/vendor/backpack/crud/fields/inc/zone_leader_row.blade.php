<div class="card mb-0" data-zone-leader-row>
    <div class="card-body">
        @if (! empty($leader['id']))<input type="hidden" name="leaders[{{ $index }}][id]" value="{{ $leader['id'] }}">@endif
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">User</label>
                <select class="form-select" name="leaders[{{ $index }}][user_id]" required>
                    <option value="">Select user</option>
                    @foreach ($field['users'] as $userId => $userName)<option value="{{ $userId }}" @selected(($leader['user_id'] ?? null) === $userId)>{{ $userName }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Leadership title</label>
                <select class="form-select" name="leaders[{{ $index }}][leadership_title_id]" required>
                    <option value="">Select title</option>
                    @foreach ($field['titles'] as $titleId => $titleName)<option value="{{ $titleId }}" @selected(($leader['leadership_title_id'] ?? null) === $titleId)>{{ $titleName }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">Start date</label><input class="form-control" type="date" name="leaders[{{ $index }}][start_date]" value="{{ $leader['start_date'] ?? '' }}" required></div>
            <div class="col-md-2"><label class="form-label">End date</label><input class="form-control" type="date" name="leaders[{{ $index }}][end_date]" value="{{ $leader['end_date'] ?? '' }}"></div>
            <div class="col-md-1 text-end"><button type="button" class="btn btn-outline-danger" data-remove-zone-leader title="Remove leader"><i class="la la-trash"></i></button></div>
            <div class="col-12">
                <input type="hidden" name="leaders[{{ $index }}][is_active]" value="0">
                <label class="form-check"><input class="form-check-input" type="checkbox" name="leaders[{{ $index }}][is_active]" value="1" @checked((bool) ($leader['is_active'] ?? true))><span class="form-check-label">Active leader</span></label>
            </div>
        </div>
    </div>
</div>
