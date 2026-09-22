@extends(backpack_view('blank'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-xxl-11">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h1 class="mb-1">Ask Data</h1>
                    <p class="text-muted mb-0">Ask in plain language. Answers only use data your role and branch scope allow you to view.</p>
                </div>
                <span class="badge bg-blue-lt text-blue"><i class="la la-shield-alt me-1"></i> Permission-aware analytics</span>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.ask-data.answer') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="ask-data-question">What would you like to know?</label>
                            <textarea
                                class="form-control @error('question') is-invalid @enderror"
                                id="ask-data-question"
                                name="question"
                                rows="3"
                                maxlength="500"
                                placeholder="For example: Compare attendance by church leaders this year"
                                required
                                data-ask-data-question
                            >{{ old('question', $question) }}</textarea>
                            @error('question')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="row g-3 align-items-end">
                            <div class="col-sm-4 col-lg-3">
                                <label class="form-label" for="ask-data-from">From <span class="text-muted">(optional)</span></label>
                                <input class="form-control @error('from') is-invalid @enderror" id="ask-data-from" name="from" type="date" value="{{ old('from', $from) }}">
                                @error('from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-4 col-lg-3">
                                <label class="form-label" for="ask-data-to">To <span class="text-muted">(optional)</span></label>
                                <input class="form-control @error('to') is-invalid @enderror" id="ask-data-to" name="to" type="date" value="{{ old('to', $to) }}">
                                @error('to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-4 col-lg-3">
                                <button class="btn btn-primary w-100" type="submit"><i class="la la-search me-1"></i> Ask</button>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="small text-muted mb-2">Try a question:</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($capabilities as $capability)
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-ask-data-example="{{ $capability['question'] }}">{{ $capability['label'] }}</button>
                                @endforeach
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            @if($result)
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                            <div>
                                <div class="text-uppercase text-muted small fw-semibold mb-1">Answer</div>
                                <h2 class="card-title fs-2 mb-2">{{ $result['title'] }}</h2>
                                <p class="fs-3 mb-0">{{ $result['answer'] }}</p>
                            </div>
                            <span class="badge bg-azure-lt text-azure">{{ $result['period'] }}</span>
                        </div>
                    </div>
                </div>

                @if($result['chart']['labels'] !== [])
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="card-title">Visual comparison</h3>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-3 mb-3" data-ask-data-legend></div>
                            <div style="min-height: 340px; position: relative;">
                                <canvas data-ask-data-chart role="img" aria-label="{{ $result['title'] }} chart"></canvas>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Supporting data</h3>
                        <span class="ms-auto text-muted small">{{ count($result['rows']) }} {{ Str::plural('row', count($result['rows'])) }}</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter table-striped card-table">
                            <thead>
                                <tr>
                                    @foreach($result['columns'] as $column)
                                        <th @class(['text-end' => $column['numeric'] ?? false])>{{ $column['label'] }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($result['rows'] as $row)
                                    <tr>
                                        @foreach($result['columns'] as $column)
                                            @php($value = $row[$column['key']] ?? '')
                                            <td @class(['text-end text-nowrap' => $column['numeric'] ?? false])>
                                                {{ ($column['numeric'] ?? false) && is_numeric($value) ? number_format((float) $value, is_float($value) ? 2 : 0) : $value }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr><td class="text-center text-muted py-5" colspan="{{ count($result['columns']) }}">No matching data was found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="alert alert-info">
                    <div class="d-flex gap-2">
                        <i class="la la-lightbulb fs-2"></i>
                        <div><strong>Tip:</strong> Mention attendance, members, income, expenses, a branch, or a church leader. Phrases such as “this year”, “last month”, and “last 30 days” are understood.</div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@pushOnce('after_scripts')
    @if($result)
        <script>window.askDataChartConfig = {{ Js::from($result['chart']) }};</script>
    @endif
    <script src="{{ asset('js/ask-data-charts.js') }}?v=1"></script>
@endPushOnce
