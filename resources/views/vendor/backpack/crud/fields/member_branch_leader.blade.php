@php
    $entryValue = isset($entry) ? $entry->{$field['name']} : null;
    $selectedLeaderId = old($field['name'], $field['value'] ?? $entryValue);
@endphp

@include('crud::fields.inc.wrapper_start')
    <label class="form-label" for="branch-leader-select">{{ $field['label'] }}</label>
    <select
        id="branch-leader-select"
        name="{{ $field['name'] }}"
        class="form-select @error($field['name']) is-invalid @enderror"
        data-member-branch-leader
        data-current-branch="{{ $field['branchId'] ?? '' }}"
    >
        <option value="">No active branch leader</option>
        @foreach ($field['leaders'] as $leader)
            <option value="{{ $leader['id'] }}" data-branch="{{ $leader['branch_id'] }}" @selected($selectedLeaderId === $leader['id'])>{{ $leader['label'] }}</option>
        @endforeach
    </select>
    @if (! empty($field['hint']))<p class="help-block">{{ $field['hint'] }}</p>@endif
    @error($field['name']) <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
@include('crud::fields.inc.wrapper_end')

@push('crud_fields_scripts')
    @bassetBlock('member-branch-leader-field.js')
    <script>
        document.querySelectorAll('[data-member-branch-leader]').forEach(function (leaderSelect) {
            if (leaderSelect.dataset.initialized) return;
            leaderSelect.dataset.initialized = 'true';

            const branchSelect = document.querySelector('[name="primary_branch_id"]');
            const applyBranchDefault = function () {
                const branchId = branchSelect?.value || leaderSelect.dataset.currentBranch;
                const leaderOptions = Array.from(leaderSelect.options).slice(1);

                leaderOptions.forEach(function (option) {
                    option.hidden = option.dataset.branch !== branchId;
                });

                const selectedOption = leaderSelect.selectedOptions[0];
                if (!selectedOption || selectedOption.value === '' || selectedOption.hidden) {
                    leaderSelect.value = leaderOptions.find((option) => !option.hidden)?.value || '';
                }
            };

            branchSelect?.addEventListener('change', applyBranchDefault);
            applyBranchDefault();
        });
    </script>
    @endBassetBlock
@endpush
