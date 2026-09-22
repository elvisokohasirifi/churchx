@extends(backpack_view('layouts.auth'))

@section('content')
    <div class="page page-center">
        <div class="container py-4" style="max-width: 980px">
            <div class="text-center mb-4">
                <h1 class="h1 mb-1">Set up your church</h1>
                <p class="text-muted">This one-time registration creates the church and its App Administrator. Registration closes immediately afterward.</p>
            </div>

            <form method="POST" action="{{ route('backpack.auth.register') }}" autocomplete="on">
                @csrf
                <div class="row g-3">
                    <div class="col-12 col-lg-7">
                        <div class="card h-100">
                            <div class="card-header"><h2 class="card-title">Church details</h2></div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label" for="church_name">Church name</label>
                                        <input id="church_name" name="church_name" value="{{ old('church_name') }}" class="form-control @error('church_name') is-invalid @enderror" required autofocus>
                                        @error('church_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="church_email">Church email</label>
                                        <input id="church_email" name="church_email" type="email" value="{{ old('church_email') }}" class="form-control @error('church_email') is-invalid @enderror">
                                        @error('church_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="church_phone">Church phone</label>
                                        <input id="church_phone" name="church_phone" type="tel" value="{{ old('church_phone') }}" class="form-control @error('church_phone') is-invalid @enderror">
                                        @error('church_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="church_website">Website</label>
                                        <input id="church_website" name="church_website" type="url" value="{{ old('church_website') }}" placeholder="https://example.org" class="form-control @error('church_website') is-invalid @enderror">
                                        @error('church_website') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="church_address">Address</label>
                                        <textarea id="church_address" name="church_address" rows="3" class="form-control @error('church_address') is-invalid @enderror">{{ old('church_address') }}</textarea>
                                        @error('church_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label" for="church_country">Country</label>
                                        <input id="church_country" name="church_country" value="{{ old('church_country', 'Ghana') }}" class="form-control @error('church_country') is-invalid @enderror" required>
                                        @error('church_country') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12 col-md-3">
                                        <label class="form-label" for="church_currency">Currency</label>
                                        <input id="church_currency" name="church_currency" value="{{ old('church_currency', 'GHS') }}" maxlength="3" class="form-control text-uppercase @error('church_currency') is-invalid @enderror" required>
                                        @error('church_currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-12 col-md-5">
                                        <label class="form-label" for="church_timezone">Timezone</label>
                                        <select id="church_timezone" name="church_timezone" class="form-select @error('church_timezone') is-invalid @enderror" required>
                                            @foreach ($timezones as $timezone)
                                                <option value="{{ $timezone }}" @selected(old('church_timezone', 'Africa/Accra') === $timezone)>{{ $timezone }}</option>
                                            @endforeach
                                        </select>
                                        @error('church_timezone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-5">
                        <div class="card h-100">
                            <div class="card-header"><h2 class="card-title">App Administrator</h2></div>
                            <div class="card-body">
                                <div class="d-grid gap-3">
                                    <div>
                                        <label class="form-label" for="name">Full name</label>
                                        <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" class="form-control @error('name') is-invalid @enderror" required>
                                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div>
                                        <label class="form-label" for="email">Email</label>
                                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" class="form-control @error('email') is-invalid @enderror" required>
                                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div>
                                        <label class="form-label" for="phone">Phone</label>
                                        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" class="form-control @error('phone') is-invalid @enderror">
                                        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-12 col-sm-6">
                                            <label class="form-label" for="password">Password</label>
                                            <input id="password" name="password" type="password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror" required>
                                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <label class="form-label" for="password_confirmation">Confirm password</label>
                                            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-12 col-sm-6">
                                            <label class="form-label" for="pin">Phone PIN <span class="text-muted">optional</span></label>
                                            <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]*" autocomplete="new-password" class="form-control @error('pin') is-invalid @enderror">
                                            @error('pin') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <label class="form-label" for="pin_confirmation">Confirm PIN</label>
                                            <input id="pin_confirmation" name="pin_confirmation" type="password" inputmode="numeric" pattern="[0-9]*" autocomplete="new-password" class="form-control">
                                        </div>
                                    </div>
                                    <div class="alert alert-info mb-0">Your account receives church-wide App Administrator access. No other person can self-register afterward.</div>
                                </div>
                            </div>
                            <div class="card-footer text-end">
                                <button type="submit" class="btn btn-primary">Create church and administrator</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
