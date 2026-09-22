<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\GoogleOAuthClient;
use App\Services\InitialRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLoginForm(InitialRegistrationService $registration, GoogleOAuthClient $google): View|RedirectResponse
    {
        if ($registration->isOpen()) {
            return redirect()->route('backpack.auth.register');
        }

        return view('admin.auth.login', [
            'title' => 'Sign in',
            'googleLoginEnabled' => $google->isConfigured(),
        ]);
    }

    public function login(AdminLoginRequest $request, AuditLogService $audit): RedirectResponse
    {
        $method = $request->string('login_method')->toString();
        $identifier = $method === 'email'
            ? Str::lower($request->string('email')->trim()->toString())
            : preg_replace('/\s+/', '', $request->string('phone')->toString());
        $rateLimitKey = 'admin-login:'.$method.':'.hash('sha256', $identifier.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($rateLimitKey, config('church.auth.max_attempts'))) {
            if ($method === 'phone') {
                $audit->record('auth.pin.locked', context: ['identifier_hash' => hash('sha256', $identifier)], request: $request);
            }

            throw ValidationException::withMessages([
                'credentials' => 'Too many attempts. Try again in '.RateLimiter::availableIn($rateLimitKey).' seconds.',
            ]);
        }

        $user = $method === 'phone'
            ? $this->attemptPhoneLogin($identifier, $request)
            : $this->attemptEmailLogin($identifier, $request);

        if (! $user instanceof User) {
            RateLimiter::hit($rateLimitKey, config('church.auth.decay_seconds'));

            if ($method === 'phone') {
                $audit->record('auth.pin.failed', context: ['identifier_hash' => hash('sha256', $identifier)], request: $request);
            }

            throw ValidationException::withMessages(['credentials' => trans('auth.failed')]);
        }

        RateLimiter::clear($rateLimitKey);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $audit->record('auth.login.succeeded', $user, context: ['method' => $method], request: $request);

        return redirect()->intended(backpack_url('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        backpack_auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('backpack.auth.login');
    }

    private function attemptEmailLogin(string $email, AdminLoginRequest $request): ?User
    {
        $authenticated = backpack_auth()->attempt([
            'email' => $email,
            'password' => $request->string('password')->toString(),
            'is_active' => true,
        ], $request->boolean('remember'));

        return $authenticated ? backpack_auth()->user() : null;
    }

    private function attemptPhoneLogin(string $phone, AdminLoginRequest $request): ?User
    {
        $user = User::query()->where('phone', $phone)->where('is_active', true)->first();

        if ($user === null || $user->pin === null || ! Hash::check($request->string('pin')->toString(), $user->pin)) {
            return null;
        }

        backpack_auth()->login($user, $request->boolean('remember'));

        return $user;
    }
}
