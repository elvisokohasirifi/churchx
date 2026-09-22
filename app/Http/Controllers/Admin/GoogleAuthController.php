<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\GoogleOAuthClient;
use App\Services\InitialRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(
        Request $request,
        GoogleOAuthClient $google,
        InitialRegistrationService $registration,
    ): RedirectResponse {
        if ($registration->isOpen()) {
            return redirect()->route('backpack.auth.register');
        }

        if (! $google->isConfigured()) {
            return redirect()->route('backpack.auth.login')
                ->withErrors(['credentials' => 'Google sign-in has not been configured.']);
        }

        $state = Str::random(64);
        $request->session()->put('google_oauth_state', $state);

        return redirect()->away($google->authorizationUrl($state));
    }

    public function callback(
        Request $request,
        GoogleOAuthClient $google,
        AuditLogService $audit,
    ): RedirectResponse {
        $expectedState = $request->session()->pull('google_oauth_state');
        $providedState = $request->string('state')->toString();

        if (! is_string($expectedState) || $providedState === '' || ! hash_equals($expectedState, $providedState)) {
            return $this->failedLogin('Your Google sign-in session expired. Please try again.');
        }

        if ($request->filled('error')) {
            return $this->failedLogin('Google sign-in was cancelled.');
        }

        $code = $request->string('code')->toString();

        if ($code === '' || ! $google->isConfigured()) {
            return $this->failedLogin('Google sign-in could not be completed. Please try again.');
        }

        try {
            $profile = $google->userFromCode($code);
        } catch (Throwable $exception) {
            report($exception);

            return $this->failedLogin('Google sign-in is temporarily unavailable. Please try again.');
        }

        $email = Str::lower(trim((string) data_get($profile, 'email')));
        $isVerified = filter_var(data_get($profile, 'email_verified'), FILTER_VALIDATE_BOOL);

        if ($email === '' || ! $isVerified) {
            $audit->record('auth.google.denied', context: ['reason' => 'unverified_email'], request: $request);

            return $this->failedLogin('Google did not provide a verified email address.');
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('is_active', true)
            ->first();

        if (! $user instanceof User) {
            $audit->record('auth.google.denied', context: [
                'reason' => 'account_not_found',
                'email_hash' => hash('sha256', $email),
            ], request: $request);

            return $this->failedLogin('No active ChurchX account matches that Google email address.');
        }

        backpack_auth()->login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $audit->record('auth.login.succeeded', $user, context: ['method' => 'google'], request: $request);

        return redirect()->intended(backpack_url('dashboard'));
    }

    private function failedLogin(string $message): RedirectResponse
    {
        return redirect()->route('backpack.auth.login')->withErrors(['credentials' => $message]);
    }
}
