@extends(backpack_view('blank'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-xl-9">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h1 class="mb-1">{{ $title }}</h1>
                    <p class="text-muted mb-0">{{ $description }}</p>
                </div>
                <a href="{{ $backRoute }}" class="btn btn-outline-secondary"><i class="la la-arrow-left me-1"></i> Back</a>
            </div>

            @if($errors->has('csv_file'))
                <div class="alert alert-danger" role="alert">
                    <h3 class="alert-title">The CSV could not be imported</h3>
                    <ul class="mb-0 ps-3">
                        @foreach($errors->get('csv_file') as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row g-4">
                <div class="col-lg-7">
                    <form method="POST" action="{{ $storeRoute }}" enctype="multipart/form-data">
                        @csrf
                        <div class="card h-100">
                            <div class="card-header"><h2 class="card-title">Upload CSV</h2></div>
                            <div class="card-body">
                                @isset($branchOptions)
                                    <label class="form-label" for="default-branch-id">Default branch</label>
                                    <select id="default-branch-id" name="default_branch_id" class="form-select @error('default_branch_id') is-invalid @enderror">
                                        <option value="">Use each row's primary_branch_code</option>
                                        @foreach ($branchOptions as $branchOptionId => $branchOptionName)
                                            <option value="{{ $branchOptionId }}" @selected(old('default_branch_id') === $branchOptionId)>{{ $branchOptionName }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-hint mt-2 mb-3">Required for compact files that use LOCATION instead of primary_branch_code.</div>
                                    @error('default_branch_id')<div class="invalid-feedback mb-3">{{ $message }}</div>@enderror
                                @endisset
                                <label class="form-label" for="csv-file">CSV file</label>
                                <input id="csv-file" name="csv_file" class="form-control @error('csv_file') is-invalid @enderror" type="file" accept=".csv,text/csv" required>
                                <div class="form-hint mt-2">Maximum file size: 5 MB. Maximum rows: 2,000.</div>
                            </div>
                            <div class="card-footer d-flex justify-content-end">
                                <button class="btn btn-primary" type="submit"><i class="la la-file-import me-1"></i> Validate and import</button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-lg-5">
                    <div class="card h-100">
                        <div class="card-header"><h2 class="card-title">CSV requirements</h2></div>
                        <div class="card-body">
                            <ul class="ps-3">
                                @foreach($columns as $instruction)
                                    <li class="mb-2">{{ $instruction }}</li>
                                @endforeach
                            </ul>
                            <a class="btn btn-outline-primary w-100" href="{{ $sampleRoute }}">
                                <i class="la la-download me-1"></i> Download sample CSV
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
