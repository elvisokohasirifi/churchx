@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>Church Settings</h2>
    </section>
@endsection

@section('content')
    @if (session('success'))
        <div class="alert alert-success" role="alert">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.church-settings.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Global church details</h3>
                    <p class="card-subtitle">These values provide branding and defaults for every branch.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-8">
                        <label class="form-label" for="name">Church name</label>
                        <input id="name" name="name" value="{{ old('name', $church->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="logo">Logo</label>
                        <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="form-control @error('logo') is-invalid @enderror">
                        @error('logo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="email">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $church->email) }}" class="form-control @error('email') is-invalid @enderror">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="phone">Phone</label>
                        <input id="phone" name="phone" value="{{ old('phone', $church->phone) }}" class="form-control @error('phone') is-invalid @enderror">
                        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="website">Website</label>
                        <input id="website" name="website" type="url" value="{{ old('website', $church->website) }}" class="form-control @error('website') is-invalid @enderror">
                        @error('website') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="country">Country</label>
                        <input id="country" name="country" value="{{ old('country', $church->country) }}" class="form-control @error('country') is-invalid @enderror">
                        @error('country') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="address">Address</label>
                        <textarea id="address" name="address" rows="3" class="form-control @error('address') is-invalid @enderror">{{ old('address', $church->address) }}</textarea>
                        @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="currency">Default currency</label>
                        <input id="currency" name="currency" maxlength="3" value="{{ old('currency', $church->currency) }}" class="form-control text-uppercase @error('currency') is-invalid @enderror" required>
                        @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12 col-md-8">
                        <label class="form-label" for="timezone">Timezone</label>
                        <select id="timezone" name="timezone" class="form-select @error('timezone') is-invalid @enderror" required>
                            @foreach ($timezones as $timezone)
                                <option value="{{ $timezone }}" @selected(old('timezone', $church->timezone) === $timezone)>{{ $timezone }}</option>
                            @endforeach
                        </select>
                        @error('timezone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary">Save church settings</button>
            </div>
        </div>
    </form>
@endsection
