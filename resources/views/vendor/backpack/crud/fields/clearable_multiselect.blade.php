@php
    $field['value'] = old_empty_or_null($field['name'], '') ?? $field['value'] ?? $field['default'] ?? [];
    $field['value'] = is_array($field['value']) ? $field['value'] : [$field['value']];
@endphp

@include('crud::fields.inc.wrapper_start')
    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
        <label class="mb-0">{!! $field['label'] !!}</label>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-clear-multiselect>
            <i class="la la-times-circle" aria-hidden="true"></i> Clear all
        </button>
    </div>
    @include('crud::fields.inc.translatable_icon')

    <input type="hidden" name="{{ $field['name'] }}" value="">
    <select
        name="{{ $field['name'] }}[]"
        multiple
        bp-field-main-input
        data-clearable-multiselect
        @include('crud::fields.inc.attributes', ['default_class' => 'form-control form-select'])
    >
        @foreach ($field['options'] as $key => $value)
            <option value="{{ $key }}" @selected(in_array($key, $field['value']))>{{ $value }}</option>
        @endforeach
    </select>

    @if (isset($field['hint']))
        <p class="help-block">{!! $field['hint'] !!}</p>
    @endif
@include('crud::fields.inc.wrapper_end')

@pushOnce('crud_fields_scripts', 'clearable-multiselect')
    <script>
        document.querySelectorAll('[data-clearable-multiselect]').forEach((select) => {
            if (select.dataset.clearableMultiselectInitialized === 'true') {
                return;
            }

            select.dataset.clearableMultiselectInitialized = 'true';
            const clearButton = select.closest('[bp-field-wrapper]')?.querySelector('[data-clear-multiselect]');

            if (!clearButton) {
                return;
            }

            const updateClearButton = () => {
                clearButton.disabled = select.selectedOptions.length === 0;
            };

            clearButton.addEventListener('click', () => {
                [...select.options].forEach((option) => { option.selected = false; });
                select.dispatchEvent(new Event('input', { bubbles: true }));
                select.dispatchEvent(new Event('change', { bubbles: true }));
                select.focus();
                updateClearButton();
            });
            select.addEventListener('change', updateClearButton);
            updateClearButton();
        });
    </script>
@endPushOnce
