@php
    $field['allows_null'] = $field['allows_null'] ?? $crud->model::isColumnNullable($field['name']);
    $field['value'] = old_empty_or_null($field['name'], '') ?? $field['value'] ?? $field['default'] ?? '';
@endphp

@include('crud::fields.inc.wrapper_start')
    <label>{!! $field['label'] !!}</label>
    @include('crud::fields.inc.translatable_icon')

    <select
        name="{{ $field['name'] }}"
        data-giving-type-default-funds='@json($field['default_funds'] ?? [])'
        data-sync-default-on-load="{{ ($field['sync_default_on_load'] ?? false) ? '1' : '0' }}"
        @include('crud::fields.inc.attributes', ['default_class' => 'form-control form-select'])
    >
        @if ($field['allows_null'])
            <option value="">-</option>
        @endif

        @foreach ($field['options'] as $key => $value)
            <option value="{{ $key }}" @selected($key == $field['value'])>{{ $value }}</option>
        @endforeach
    </select>

    @if (isset($field['hint']))
        <p class="help-block">{!! $field['hint'] !!}</p>
    @endif
@include('crud::fields.inc.wrapper_end')

@pushOnce('crud_fields_scripts', 'giving-type-default-fund')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('select[data-giving-type-default-funds]').forEach((givingTypeSelect) => {
                const defaultFunds = JSON.parse(givingTypeSelect.dataset.givingTypeDefaultFunds || '{}');
                const fundSelect = document.querySelector('select[name="fund_id"]');

                if (!fundSelect) {
                    return;
                }

                const selectDefaultFund = () => {
                    const defaultFundId = defaultFunds[givingTypeSelect.value];

                    if (defaultFundId && fundSelect.querySelector(`option[value="${CSS.escape(defaultFundId)}"]`)) {
                        fundSelect.value = defaultFundId;
                        fundSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                };

                givingTypeSelect.addEventListener('change', selectDefaultFund);

                if (givingTypeSelect.dataset.syncDefaultOnLoad === '1') {
                    selectDefaultFund();
                }
            });
        });
    </script>
@endPushOnce
