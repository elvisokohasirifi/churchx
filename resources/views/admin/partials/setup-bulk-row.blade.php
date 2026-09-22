<tr data-bulk-setup-row>
    @foreach ($definition['fields'] as $name => $field)
        @php
            $value = $item[$name] ?? $field['default'];
            $required = in_array('required', $field['rules'], true);
            $errorKey = is_numeric($index) ? "items.$index.$name" : null;
        @endphp
        <td class="align-top" style="min-width: 180px">
            @if (in_array($field['type'], ['select', 'boolean'], true))
                <select class="form-select @if($errorKey && $errors->has($errorKey)) is-invalid @endif" name="items[{{ $index }}][{{ $name }}]" @required($required)>
                    @if ($field['type'] === 'select' && !$required)
                        <option value="">—</option>
                    @endif
                    @foreach ($field['options'] as $optionValue => $optionLabel)
                        <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                    @endforeach
                </select>
            @elseif ($field['type'] === 'textarea')
                <textarea class="form-control @if($errorKey && $errors->has($errorKey)) is-invalid @endif" name="items[{{ $index }}][{{ $name }}]" rows="2" @required($required)>{{ $value }}</textarea>
            @else
                <input
                    class="form-control @if($errorKey && $errors->has($errorKey)) is-invalid @endif"
                    type="{{ $field['type'] }}"
                    name="items[{{ $index }}][{{ $name }}]"
                    value="{{ $value }}"
                    @if ($field['type'] === 'number') step="0.0001" min="0" @endif
                    @required($required)
                >
            @endif
            @if ($errorKey)
                @error($errorKey)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            @endif
        </td>
    @endforeach
    <td class="align-top text-end">
        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-bulk-setup-row aria-label="Remove row">
            <i class="la la-trash" aria-hidden="true"></i>
        </button>
    </td>
</tr>
