<?php

use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserAccessGrantedNotification;
use App\PermissionCode;
use Illuminate\Support\Facades\Notification;

function initialRegistrationData(array $overrides = []): array
{
    return array_merge([
        'church_name' => 'Grace Community Church',
        'church_email' => 'office@grace.test',
        'church_phone' => '+233200000000',
        'church_website' => 'https://grace.test',
        'church_address' => '1 Church Street',
        'church_country' => 'Ghana',
        'church_currency' => 'ghs',
        'church_timezone' => 'Africa/Accra',
        'name' => 'Initial Administrator',
        'email' => 'ADMIN@grace.test',
        'phone' => '+233 24 000 0000',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
        'pin' => '4826',
        'pin_confirmation' => '4826',
    ], $overrides);
}

it('directs an unconfigured installation to one-time registration', function () {
    $this->get(route('backpack.auth.login'))
        ->assertRedirect(route('backpack.auth.register'));

    $this->get(route('backpack.auth.register'))
        ->assertOk()
        ->assertSee('Set up your church');
});

it('registers the first user as church-wide app administrator and configures the church', function () {
    Notification::fake();
    $existingChurch = Church::factory()->create(['name' => 'Placeholder']);

    $response = $this->post(route('backpack.auth.register'), initialRegistrationData());

    $response->assertRedirect(route('admin.dashboard'));
    $user = User::query()->where('email', 'admin@grace.test')->firstOrFail();
    $this->assertAuthenticatedAs($user, 'backpack');
    expect(Church::query()->count())->toBe(1)
        ->and($existingChurch->fresh()->name)->toBe('Grace Community Church')
        ->and($existingChurch->fresh()->currency)->toBe('GHS')
        ->and(data_get($existingChurch->fresh()->settings, 'initial_registration_completed'))->toBeTrue()
        ->and($user->phone)->toBe('+233240000000')
        ->and($user->roles()->where('name', 'App Administrator')->wherePivotNull('branch_id')->exists())->toBeTrue()
        ->and($user->roles()->firstOrFail()->permissions()->count())->toBe(count(PermissionCode::cases()))
        ->and(Role::query()->count())->toBe(8)
        ->and(AuditLog::query()->where('action', 'church.initial_registration')->exists())->toBeTrue();
    Notification::assertSentTo($user, UserAccessGrantedNotification::class);
});

it('closes every registration endpoint after the first user exists', function () {
    User::factory()->create()->delete();

    $this->get(route('backpack.auth.register'))->assertNotFound();
    $this->post(route('backpack.auth.register'), initialRegistrationData())->assertNotFound();
    $this->get(route('backpack.auth.login'))->assertOk();

    expect(User::withTrashed()->count())->toBe(1);
});

it('does not create partial setup data when registration validation fails', function () {
    $this->post(route('backpack.auth.register'), initialRegistrationData([
        'church_name' => '',
        'password_confirmation' => 'different-password',
    ]))->assertSessionHasErrors(['church_name', 'password']);

    expect(User::query()->exists())->toBeFalse()
        ->and(Church::query()->exists())->toBeFalse();
});
