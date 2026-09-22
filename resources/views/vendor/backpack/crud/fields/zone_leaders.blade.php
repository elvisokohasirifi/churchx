@php
    $storedLeaders = isset($entry)
        ? $entry->leaders->map(fn ($leader) => [
            'id' => $leader->id,
            'user_id' => $leader->user_id,
            'leadership_title_id' => $leader->leadership_title_id,
            'start_date' => $leader->start_date?->format('Y-m-d'),
            'end_date' => $leader->end_date?->format('Y-m-d'),
            'is_active' => $leader->is_active,
        ])->all()
        : [];
    $leaderRows = old('leaders', $storedLeaders);
@endphp

@include('crud::fields.inc.wrapper_start')
    <input type="hidden" name="leaders_present" value="1">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <div>
            <label class="mb-0">{{ $field['label'] }}</label>
            <p class="help-block mb-0">Optionally appoint one or more users to lead this zone.</p>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary" data-add-zone-leader>
            <i class="la la-plus"></i> Add leader
        </button>
    </div>

    @if ($errors->has('leaders') || $errors->has('leaders.*'))
        <div class="alert alert-danger py-2">
            @foreach ($errors->get('leaders') as $message)<div>{{ $message }}</div>@endforeach
            @foreach ($errors->get('leaders.*') as $messages)
                @foreach ($messages as $message)<div>{{ $message }}</div>@endforeach
            @endforeach
        </div>
    @endif

    <div class="d-flex flex-column gap-3" data-zone-leaders data-next-index="{{ count($leaderRows) }}">
        @foreach ($leaderRows as $index => $leader)
            @include('crud::fields.inc.zone_leader_row', ['index' => $index, 'leader' => $leader])
        @endforeach
    </div>

    <template data-zone-leader-template>
        @include('crud::fields.inc.zone_leader_row', [
            'index' => '__INDEX__',
            'leader' => ['id' => null, 'user_id' => null, 'leadership_title_id' => null, 'start_date' => now()->toDateString(), 'end_date' => null, 'is_active' => true],
        ])
    </template>
@include('crud::fields.inc.wrapper_end')

@push('crud_fields_scripts')
    @bassetBlock('zone-leaders-field.js')
    <script>
        document.querySelectorAll('[data-add-zone-leader]').forEach(function (button) {
            if (button.dataset.initialized) return;
            button.dataset.initialized = 'true';

            const field = button.closest('[bp-field-wrapper]');
            const rows = field.querySelector('[data-zone-leaders]');
            const template = field.querySelector('[data-zone-leader-template]');

            button.addEventListener('click', function () {
                const index = rows.dataset.nextIndex;
                rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', index));
                rows.dataset.nextIndex = String(Number(index) + 1);
            });

            rows.addEventListener('click', function (event) {
                const removeButton = event.target.closest('[data-remove-zone-leader]');
                if (removeButton) removeButton.closest('[data-zone-leader-row]').remove();
            });
        });
    </script>
    @endBassetBlock
@endpush
