@extends(backpack_view('blank'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-lg-9">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Convert {{ $visitor->name }} to a member</h3></div>
                <form method="POST" action="{{ route('admin.visitors.convert.store', $visitor) }}">
                    @csrf
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-4"><label class="form-label" for="first_name">First name</label><input id="first_name" name="first_name" value="{{ old('first_name', $firstName) }}" class="form-control" required></div>
                            <div class="col-12 col-md-4"><label class="form-label" for="middle_name">Middle name</label><input id="middle_name" name="middle_name" value="{{ old('middle_name', $middleName) }}" class="form-control"></div>
                            <div class="col-12 col-md-4"><label class="form-label" for="last_name">Last name</label><input id="last_name" name="last_name" value="{{ old('last_name', $lastName) }}" class="form-control" required></div>
                            <div class="col-12 col-md-6"><label class="form-label" for="phone">Phone</label><input id="phone" name="phone" value="{{ old('phone', $visitor->phone) }}" class="form-control"></div>
                            <div class="col-12 col-md-6"><label class="form-label" for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email', $visitor->email) }}" class="form-control"></div>
                            <div class="col-12 col-md-6"><label class="form-label" for="date_joined">Date joined</label><input id="date_joined" name="date_joined" type="date" value="{{ old('date_joined', today()->toDateString()) }}" class="form-control" required></div>
                            <div class="col-12 col-md-6"><label class="form-label" for="membership_status">Membership status</label><select id="membership_status" name="membership_status" class="form-select" required>@foreach ($statuses as $status)<option value="{{ $status->value }}">{{ str($status->value)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('visitors.show', $visitor) }}">Cancel</a>
                        <button class="btn btn-primary" type="submit">Create member</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
