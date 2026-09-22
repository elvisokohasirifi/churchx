<?php

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.google', [
        'client_id' => 'google-client-id',
        'client_secret' => 'google-client-secret',
        'redirect_uri' => 'http://localhost/admin/login/google/callback',
    ]);
});

it('offers google login when credentials are configured', function () {
    User::factory()->create();

    $response = $this->get(route('backpack.auth.login'));

    $response
        ->assertOk()
        ->assertSee('Continue with Google')
        ->assertSee(route('backpack.auth.google.redirect'));
});

it('redirects to google with a session-bound state value', function () {
    User::factory()->create();

    $response = $this->get(route('backpack.auth.google.redirect'));

    $response->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth?');
    $state = session('google_oauth_state');
    $query = [];
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($state)->toBeString()->not->toBeEmpty()
        ->and($query['state'])->toBe($state)
        ->and($query['client_id'])->toBe('google-client-id')
        ->and($query['scope'])->toBe('openid email profile');
});

it('signs in an existing active user with a verified matching google email', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token']),
        'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
            'sub' => 'google-user-id',
            'email' => 'LEADER@example.com',
            'email_verified' => true,
            'name' => 'Church Leader',
        ]),
    ]);
    $user = User::factory()->create(['email' => 'leader@example.com', 'is_active' => true]);
    $this->withSession(['google_oauth_state' => 'valid-state']);

    $response = $this->get(route('backpack.auth.google.callback', [
        'state' => 'valid-state',
        'code' => 'authorization-code',
    ]));

    $response->assertRedirect(backpack_url('dashboard'));
    $this->assertAuthenticatedAs($user, 'backpack');
    expect($user->fresh()->last_login_at)->not->toBeNull();
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'action' => 'auth.login.succeeded',
    ]);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
        && $request['client_secret'] === 'google-client-secret');
});

it('does not create an account for an unknown google user', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token']),
        'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
            'sub' => 'unknown-google-user',
            'email' => 'unknown@example.com',
            'email_verified' => true,
        ]),
    ]);
    $this->withSession(['google_oauth_state' => 'valid-state']);

    $response = $this->get(route('backpack.auth.google.callback', [
        'state' => 'valid-state',
        'code' => 'authorization-code',
    ]));

    $response
        ->assertRedirect(route('backpack.auth.login'))
        ->assertSessionHasErrors('credentials');
    $this->assertGuest('backpack');
    expect(User::query()->count())->toBe(0);
});

it('rejects a callback whose state does not match the session', function () {
    Http::preventStrayRequests();
    $this->withSession(['google_oauth_state' => 'expected-state']);

    $response = $this->get(route('backpack.auth.google.callback', [
        'state' => 'different-state',
        'code' => 'authorization-code',
    ]));

    $response
        ->assertRedirect(route('backpack.auth.login'))
        ->assertSessionHasErrors('credentials');
    $this->assertGuest('backpack');
    Http::assertNothingSent();
});

it('hides google login when credentials are absent', function () {
    User::factory()->create();
    config()->set('services.google.client_id');
    config()->set('services.google.client_secret');

    $response = $this->get(route('backpack.auth.login'));

    $response->assertOk()->assertDontSee('Continue with Google');
});

it('preserves first-run registration before google login is available', function () {
    $response = $this->get(route('backpack.auth.google.redirect'));

    $response->assertRedirect(route('backpack.auth.register'));
});
