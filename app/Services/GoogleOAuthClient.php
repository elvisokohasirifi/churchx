<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleOAuthClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    public function redirectUri(): string
    {
        return config('services.google.redirect_uri') ?: route('backpack.auth.google.callback');
    }

    public function authorizationUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array<string, mixed> */
    public function userFromCode(string $code): array
    {
        $token = Http::asForm()
            ->connectTimeout(3)
            ->timeout(10)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUri(),
            ])
            ->throw()
            ->json();

        $accessToken = data_get($token, 'access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new \UnexpectedValueException('Google did not return an access token.');
        }

        /** @var array<string, mixed> $profile */
        $profile = Http::withToken($accessToken)
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->get('https://openidconnect.googleapis.com/v1/userinfo')
            ->throw()
            ->json();

        return $profile;
    }
}
