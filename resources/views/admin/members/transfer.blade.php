@extends(backpack_view('blank'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Transfer {{ $member->full_name }}</h3></div>
                <form method="POST" action="{{ route('admin.members.transfer.store', $member) }}">
                    @csrf
                    <div class="card-body">
                        <p class="text-muted">The current primary membership will be closed and retained in branch history.</p>
                        <div class="row g-3">
                            <div class="col-12 col-md-7">
                                <label class="form-label" for="branch_id">New branch</label>
                                <select id="branch_id" name="branch_id" class="form-select @error('branch_id') is-invalid @enderror" required>
                                    <option value="">Select a branch</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected(old('branch_id') === $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                @error('branch_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12 col-md-5">
                                <label class="form-label" for="transfer_date">Transfer date</label>
                                <input id="transfer_date" name="transfer_date" type="date" value="{{ old('transfer_date', today()->toDateString()) }}" class="form-control @error('transfer_date') is-invalid @enderror" required>
                                @error('transfer_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="branch_leader_id">New branch leader</label>
                                <select id="branch_leader_id" name="branch_leader_id" class="form-select @error('branch_leader_id') is-invalid @enderror" required>
                                    <option value="">Select a branch leader</option>
                                    @foreach ($branchLeaders as $leader)
                                        <option value="{{ $leader->id }}" data-branch-id="{{ $leader->branch_id }}" @selected(old('branch_leader_id') === $leader->id)>{{ $leader->member->full_name }} — {{ $leader->leadershipTitle->name }}</option>
                                    @endforeach
                                </select>
                                @error('branch_leader_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('members.show', $member) }}">Cancel</a>
                        <button class="btn btn-primary" type="submit">Confirm transfer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('after_scripts')
    <script>
        const branchSelect = document.getElementById('branch_id');
        const leaderSelect = document.getElementById('branch_leader_id');

        function filterBranchLeaders() {
            const branchId = branchSelect.value;
            Array.from(leaderSelect.options).forEach(function (option, index) {
                option.hidden = index > 0 && option.dataset.branchId !== branchId;
            });

            if (leaderSelect.selectedOptions[0]?.hidden) leaderSelect.value = '';
        }

        branchSelect.addEventListener('change', filterBranchLeaders);
        filterBranchLeaders();
    </script>
@endpush
