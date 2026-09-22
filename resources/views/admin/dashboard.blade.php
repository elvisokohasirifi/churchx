@extends(backpack_view('blank'))
@section('content')
    <h2 class="mb-3">Dashboard</h2>

    @if($operationalCards->isNotEmpty() || $latestAttendance)
        <div class="row g-3">
            @foreach($operationalCards as [$label, $value, $color])
                <div class="col-6 col-lg-3">
                    <div class="card {{ $color }} text-white">
                        <div class="card-body">
                            <div class="opacity-75">{{ $label }}</div>
                            <div class="display-6">{{ $value }}</div>
                        </div>
                    </div>
                </div>
            @endforeach

            @if($latestAttendance)
                <div class="col-6 col-lg-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="opacity-75">Latest attendance</div>
                            <div class="display-6">{{ $latestAttendance->total_attendance }}</div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if($finance)
        <h3 class="{{ $operationalCards->isNotEmpty() || $latestAttendance ? 'mt-4' : '' }}">Finance</h3>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card bg-teal text-white">
                    <div class="card-body">
                        <span class="opacity-75">Completed income</span>
                        <div class="fs-1">{{ number_format((float) $finance['income'], 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <span class="opacity-75">Paid expenses</span>
                        <div class="fs-1">{{ number_format((float) $finance['expenses'], 2) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h3 class="card-title mb-0">20 most recent incomes</h3>
                        <a class="btn btn-sm btn-outline-primary" href="{{ backpack_url('income') }}">View all</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Branch</th>
                                    <th>Giving</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentIncomes as $income)
                                    <tr>
                                        <td class="text-nowrap">{{ $income->date->format('d M Y') }}</td>
                                        <td>{{ $income->branch?->name ?? 'Church-wide' }}</td>
                                        <td>
                                            <div>{{ $income->givingType?->name ?? 'Income' }}</div>
                                            <small class="text-muted">{{ $income->giver_name ?: $income->receipt_number }}</small>
                                        </td>
                                        <td class="text-end text-nowrap fw-semibold">{{ $income->currency }} {{ number_format((float) $income->amount, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-4">No income recorded yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h3 class="card-title mb-0">20 most recent expenses</h3>
                        <a class="btn btn-sm btn-outline-primary" href="{{ backpack_url('expenses') }}">View all</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Branch</th>
                                    <th>Expense</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentExpenses as $expense)
                                    <tr>
                                        <td class="text-nowrap">{{ $expense->date->format('d M Y') }}</td>
                                        <td>{{ $expense->branch?->name ?? 'Church-wide' }}</td>
                                        <td>
                                            <div>{{ $expense->expenseType?->name ?? 'Expense' }}</div>
                                            <small class="text-muted">{{ $expense->recipient_name }}</small>
                                        </td>
                                        <td class="text-end text-nowrap fw-semibold">{{ $expense->currency }} {{ number_format((float) $expense->amount, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-4">No expenses recorded yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
