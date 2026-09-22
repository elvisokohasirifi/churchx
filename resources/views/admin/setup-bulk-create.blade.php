@extends(backpack_view('blank'))

@php
    $defaultItem = collect($definition['fields'])->mapWithKeys(fn (array $field, string $name): array => [$name => $field['default']])->all();
    $items = old('items', [$defaultItem]);
    $nextIndex = $items === [] ? 0 : max(array_map('intval', array_keys($items))) + 1;
@endphp

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h2 class="mb-1">Bulk add {{ $definition['label'] }}</h2>
            <p class="text-muted mb-0">Add up to 100 {{ strtolower($definition['label']) }} in one submission.</p>
        </div>
        <a href="{{ route($resource.'.index') }}" class="btn btn-outline-secondary">
            <i class="la la-arrow-left" aria-hidden="true"></i> Back to {{ $definition['label'] }}
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            Please correct the highlighted rows. No records have been created yet.
        </div>
    @endif

    <form method="POST" action="{{ route('admin.setup.bulk.store', ['resource' => $resource]) }}">
        @csrf
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between gap-3">
                <h3 class="card-title mb-0">{{ $definition['label'] }}</h3>
                <button type="button" class="btn btn-sm btn-outline-primary" data-add-bulk-setup-row>
                    <i class="la la-plus" aria-hidden="true"></i> Add row
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter mb-0">
                    <thead>
                        <tr>
                            @foreach ($definition['fields'] as $field)
                                <th>{{ $field['label'] }}</th>
                            @endforeach
                            <th class="text-end">Remove</th>
                        </tr>
                    </thead>
                    <tbody data-bulk-setup-rows data-next-index="{{ $nextIndex }}">
                        @foreach ($items as $index => $item)
                            @include('admin.partials.setup-bulk-row', ['index' => $index, 'item' => $item])
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center gap-3">
                <span class="text-muted">The entire batch is validated before anything is saved.</span>
                <button type="submit" class="btn btn-primary">
                    <i class="la la-save" aria-hidden="true"></i> Create all
                </button>
            </div>
        </div>
    </form>

    <template data-bulk-setup-row-template>
        @include('admin.partials.setup-bulk-row', ['index' => '__INDEX__', 'item' => $defaultItem])
    </template>
@endsection

@push('after_scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const rows = document.querySelector('[data-bulk-setup-rows]');
            const template = document.querySelector('[data-bulk-setup-row-template]');
            const addButton = document.querySelector('[data-add-bulk-setup-row]');

            if (!rows || !template || !addButton) {
                return;
            }

            const updateRemoveButtons = () => {
                const buttons = rows.querySelectorAll('[data-remove-bulk-setup-row]');
                buttons.forEach((button) => { button.disabled = buttons.length === 1; });
            };

            addButton.addEventListener('click', () => {
                const index = rows.dataset.nextIndex;
                rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', index));
                rows.dataset.nextIndex = String(Number(index) + 1);
                updateRemoveButtons();
                rows.lastElementChild?.querySelector('input, select, textarea')?.focus();
            });

            rows.addEventListener('click', (event) => {
                const removeButton = event.target.closest('[data-remove-bulk-setup-row]');

                if (removeButton && rows.children.length > 1) {
                    removeButton.closest('[data-bulk-setup-row]').remove();
                    updateRemoveButtons();
                }
            });

            updateRemoveButtons();
        });
    </script>
@endpush
