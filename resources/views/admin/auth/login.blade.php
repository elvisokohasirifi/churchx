@extends(backpack_view('layouts.auth'))

@section('content')
    <div class="page page-center">
        <div class="container py-4" style="max-width: 880px">
            <div class="text-center mb-4 display-6 auth-logo-container">
                {!! backpack_theme_config('project_logo') !!}
            </div>

            @if ($errors->has('credentials'))
                <div class="alert alert-danger" role="alert">{{ $errors->first('credentials') }}</div>
            @endif

            @if($googleLoginEnabled)
                <div class="card card-md mb-3">
                    <div class="card-body">
                        <a class="btn btn-outline-secondary w-100" href="{{ route('backpack.auth.google.redirect') }}">
                            <svg class="me-2" width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
                                <path fill="#4285F4" d="M17.64 9.205c0-.638-.057-1.252-.164-1.841H9v3.482h4.844a4.14 4.14 0 0 1-1.797 2.716v2.258h2.909c1.702-1.567 2.684-3.875 2.684-6.615Z"/>
                                <path fill="#34A853" d="M9 18c2.43 0 4.468-.806 5.956-2.18l-2.909-2.258c-.806.54-1.836.86-3.047.86-2.344 0-4.328-1.585-5.037-3.714H.956v2.332A9 9 0 0 0 9 18Z"/>
                                <path fill="#FBBC05" d="M3.963 10.708A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.281-1.708V4.96H.956A9 9 0 0 0 0 9c0 1.452.347 2.827.956 4.04l3.007-2.332Z"/>
                                <path fill="#EA4335" d="M9 3.578c1.322 0 2.508.454 3.441 1.346l2.582-2.582C13.464.89 11.426 0 9 0A9 9 0 0 0 .956 4.96l3.007 2.332C4.672 5.163 6.656 3.578 9 3.578Z"/>
                            </svg>
                            Continue with Google
                        </a>
                        <p class="text-muted text-center small mb-0 mt-2">Use the Google account matching your existing ChurchX email.</p>
                    </div>
                </div>

                <div class="text-center text-muted mb-3">or sign in with your ChurchX credentials</div>
            @endif

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="card card-md h-100">
                        <div class="card-body">
                            <h2 class="h2 mb-1">Email sign in</h2>
                            <p class="text-muted mb-4">Use your administrator email and password.</p>
                            <form method="POST" action="{{ route('backpack.auth.login') }}" autocomplete="on">
                                @csrf
                                <input type="hidden" name="login_method" value="email">

                                <div class="mb-3">
                                    <label class="form-label" for="email">Email</label>
                                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" autofocus class="form-control @error('email') is-invalid @enderror">
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="password">Password</label>
                                    <input id="password" name="password" type="password" autocomplete="current-password" class="form-control @error('password') is-invalid @enderror">
                                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <label class="form-check mb-3">
                                    <input name="remember" value="1" type="checkbox" class="form-check-input">
                                    <span class="form-check-label">Remember me</span>
                                </label>

                                <button type="submit" class="btn btn-primary w-100">Sign in with email</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="card card-md h-100">
                        <div class="card-body">
                            <h2 class="h2 mb-1">Phone sign in</h2>
                            <p class="text-muted mb-4">Use your registered phone number and PIN.</p>
                            <form method="POST" action="{{ route('backpack.auth.login') }}" autocomplete="on">
                                @csrf
                                <input type="hidden" name="login_method" value="phone">

                                <div class="mb-3">
                                    <label class="form-label" for="phone">Phone</label>
                                    <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" class="form-control @error('phone') is-invalid @enderror">
                                    @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="pin">PIN</label>
                                    <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]*" autocomplete="current-password" class="form-control @error('pin') is-invalid @enderror">
                                    @error('pin') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <label class="form-check mb-3">
                                    <input name="remember" value="1" type="checkbox" class="form-check-input">
                                    <span class="form-check-label">Remember me</span>
                                </label>

                                <button type="submit" class="btn btn-primary w-100">Sign in with phone</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
