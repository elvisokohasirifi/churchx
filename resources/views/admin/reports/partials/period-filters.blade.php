<form method="GET" class="card card-body mb-3">
    <div class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label" for="report-from">From</label>
            <input class="form-control" id="report-from" type="date" name="from" value="{{ $from->toDateString() }}">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="report-to">To</label>
            <input class="form-control" id="report-to" type="date" name="to" value="{{ $to->toDateString() }}">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="report-branch">Branch</label>
            <select class="form-select" id="report-branch" name="branch_id">
                <option value="">All accessible branches</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary flex-fill" type="submit"><i class="la la-filter"></i> Apply</button>
            <a class="btn btn-outline-secondary" href="{{ url()->current() }}" title="Clear filters" aria-label="Clear filters"><i class="la la-times"></i></a>
        </div>
        @if ($showSearch ?? false)
            <div class="col-md-8">
                <label class="form-label" for="report-search">Search members</label>
                <input class="form-control" id="report-search" type="search" name="q" value="{{ request('q') }}" placeholder="Name, membership number or phone">
            </div>
        @endif
        @if ($showMinimumAbsences ?? false)
            <div class="col-md-4">
                <label class="form-label" for="minimum-absences">Minimum absences</label>
                <input class="form-control" id="minimum-absences" type="number" name="minimum_absences" min="2" max="52" value="{{ $minimumAbsences }}">
            </div>
        @endif
    </div>
</form>
