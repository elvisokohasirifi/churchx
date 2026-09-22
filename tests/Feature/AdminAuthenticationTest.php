<?php

use App\Models\AuditLog;
use App\Models\User;

it('authenticates an active user with email and password', function () {
    $user = User::factory()->create(['email' => 'admin@example.com', 'password' => 'correct-password']);

    $response = $this->post(route('backpack.auth.login'), [
        'login_method' => 'email',
        'email' => 'ADMIN@example.com',
        'password' => 'correct-password',
    ]);

    $response->assertRedirect(backpack_url('dashboard'));
    $this->assertAuthenticatedAs($user, 'backpack');
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('authenticates an active user with phone and a hashed pin', function () {
    $user = User::factory()->create(['phone' => '+15550001111', 'pin' => '4826']);

    $response = $this->post(route('backpack.auth.login'), [
        'login_method' => 'phone',
        'phone' => '+15550001111',
        'pin' => '4826',
    ]);

    $response->assertRedirect(backpack_url('dashboard'));
    $this->assertAuthenticatedAs($user, 'backpack');
    expect($user->getRawOriginal('pin'))->not->toBe('4826');
});

it('blocks inactive users from both authentication methods', function (array $credentials) {
    User::factory()->create([
        'email' => 'inactive@example.com',
        'phone' => '+15550002222',
        'password' => 'correct-password',
        'pin' => '4826',
        'is_active' => false,
    ]);

    $response = $this->post(route('backpack.auth.login'), $credentials);

    $response->assertSessionHasErrors('credentials');
    $this->assertGuest('backpack');
})->with([
    'email and password' => [[
        'login_method' => 'email', 'email' => 'inactive@example.com', 'password' => 'correct-password',
    ]],
    'phone and PIN' => [[
        'login_method' => 'phone', 'phone' => '+15550002222', 'pin' => '4826',
    ]],
]);

it('temporarily locks and audits repeated bad pin attempts', function () {
    config(['church.auth.max_attempts' => 2, 'church.auth.decay_seconds' => 120]);
    User::factory()->create(['phone' => '+15550003333', 'pin' => '4826']);
    $credentials = ['login_method' => 'phone', 'phone' => '+15550003333', 'pin' => '9999'];

    $this->post(route('backpack.auth.login'), $credentials)->assertSessionHasErrors('credentials');
    $this->post(route('backpack.auth.login'), $credentials)->assertSessionHasErrors('credentials');
    $response = $this->post(route('backpack.auth.login'), $credentials);

    $response->assertSessionHasErrors('credentials');
    $this->assertGuest('backpack');
    expect(AuditLog::query()->where('action', 'auth.pin.failed')->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'auth.pin.locked')->count())->toBe(1);
});
