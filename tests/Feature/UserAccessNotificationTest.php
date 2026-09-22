<?php

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Notifications\UserAccessGrantedNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

it('shows a clear-all control for branch scope when creating a user', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');

    $this->actingAs($administrator, 'backpack')
        ->get(route('users.create'))
        ->assertSee('name="branch_ids[]"', false)
        ->assertSee('data-clear-multiselect', false)
        ->assertSee('Clear all');
});

it('creates an administrator-added user with a role and emails sign-in guidance', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    Notification::fake();
    $firstBranch = Branch::factory()->create();
    $secondBranch = Branch::factory()->create();
    $role = Role::query()->where('name', 'Branch Administrator')->firstOrFail();

    $response = $this->actingAs($administrator, 'backpack')->post(route('users.store'), [
        'name' => 'New Branch User',
        'email' => 'branch.user@example.test',
        'phone' => '+233240000010',
        'password' => 'secure-password',
        'pin' => '5678',
        'is_active' => true,
        'role_id' => $role->id,
        'branch_ids' => [$firstBranch->id, $secondBranch->id],
        'save_action' => 'save_and_back',
    ]);

    $response->assertRedirect();
    $user = User::query()->where('email', 'branch.user@example.test')->firstOrFail();
    $this->assertDatabaseHas('user_roles', ['user_id' => $user->id, 'role_id' => $role->id, 'branch_id' => $firstBranch->id, 'is_active' => true]);
    $this->assertDatabaseHas('user_roles', ['user_id' => $user->id, 'role_id' => $role->id, 'branch_id' => $secondBranch->id, 'is_active' => true]);
    Notification::assertSentTo($user, UserAccessGrantedNotification::class, function (UserAccessGrantedNotification $notification, array $channels) use ($role, $firstBranch): bool {
        return $channels === ['mail']
            && $notification->assignment->role_id === $role->id
            && $notification->assignment->branch_id === $firstBranch->id;
    });
    Notification::assertSentTo($user, UserAccessGrantedNotification::class, function (UserAccessGrantedNotification $notification, array $channels) use ($role, $secondBranch): bool {
        return $channels === ['mail']
            && $notification->assignment->role_id === $role->id
            && $notification->assignment->branch_id === $secondBranch->id;
    });
});

it('renders the assigned role and sign-in instructions without credentials', function () {
    Notification::fake();
    $user = User::factory()->create(['name' => 'A <script>alert(1)</script> User']);
    $role = Role::factory()->create(['name' => 'Department Leader']);
    $assignment = UserRole::query()->create(['user_id' => $user->id, 'role_id' => $role->id, 'is_active' => true]);

    $html = (new UserAccessGrantedNotification($assignment))->toMail($user)->render();

    expect(new UserAccessGrantedNotification($assignment))->toBeInstanceOf(ShouldQueue::class)
        ->and(str_contains($html, 'Department Leader'))->toBeTrue()
        ->and(str_contains($html, 'Sign in'))->toBeTrue()
        ->and(str_contains($html, route('backpack.auth.login')))->toBeTrue()
        ->and(str_contains($html, 'password123'))->toBeFalse()
        ->and(str_contains($html, '<script>alert(1)</script>'))->toBeFalse();
});
