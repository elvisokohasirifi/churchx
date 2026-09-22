<?php

use App\Jobs\RunApplicationBackup;
use App\Models\Branch;
use App\Models\Church;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Church::factory()->create();
});

it('shows every files and logs screen to app administrators', function (string $routeName) {
    Storage::fake('local');
    $user = User::factory()->create();
    assignRole($user, 'App Administrator');

    $response = $this->actingAs($user, 'backpack')->get(route($routeName));

    $response->assertOk()->assertSee('Files &amp; Logs', false);
})->with([
    'activity log' => 'admin.activity-logs.index',
    'error logs' => 'admin.error-logs.index',
    'file system' => 'admin.file-system.index',
    'backups' => 'admin.backups.index',
]);

it('denies files and logs screens to non app administrators', function (string $routeName) {
    Storage::fake('local');
    $user = User::factory()->create();
    assignRole($user, 'Church Overseer');

    $this->actingAs($user, 'backpack')->get(route($routeName))->assertForbidden();
})->with([
    'activity log' => 'admin.activity-logs.index',
    'system audit logs' => 'audit-logs.index',
    'error logs' => 'admin.error-logs.index',
    'file system' => 'admin.file-system.index',
    'backups' => 'admin.backups.index',
]);

it('records model changes without logging user credentials', function () {
    $user = User::factory()->create();
    assignRole($user, 'App Administrator');
    $this->actingAs($user, 'backpack');

    $branch = Branch::factory()->create(['name' => 'Central Campus']);
    $user->update(['password' => 'a-new-secret-password']);

    $branchActivity = Activity::query()->forSubject($branch)->latest()->firstOrFail();
    $userActivity = Activity::query()->forSubject($user)->latest()->firstOrFail();

    expect($branchActivity->event)->toBe('created')
        ->and($branchActivity->causer_id)->toBe($user->id)
        ->and($userActivity->attribute_changes->toArray())->not->toHaveKey('attributes.password');

    $this->get(route('admin.activity-logs.index'))->assertOk()->assertSee('Central Campus');
});

it('only browses files inside approved storage disks', function () {
    Storage::fake('local');
    Storage::disk('local')->put('documents/report.txt', 'report contents');
    $user = User::factory()->create();
    assignRole($user, 'App Administrator');

    $this->actingAs($user, 'backpack')
        ->get(route('admin.file-system.index', ['disk' => 'local', 'path' => 'documents']))
        ->assertOk()
        ->assertSee('report.txt');

    $this->get(route('admin.file-system.index', ['disk' => 'local', 'path' => '../']))->assertNotFound();
    $this->get(route('admin.file-system.index', ['disk' => 'unapproved']))->assertNotFound();
});

it('escapes error log contents', function () {
    $logName = 'files-and-logs-test.log';
    $logPath = storage_path('logs/'.$logName);
    File::put($logPath, '<script>alert("unsafe")</script>');
    $user = User::factory()->create();
    assignRole($user, 'App Administrator');

    try {
        $this->actingAs($user, 'backpack')
            ->get(route('admin.error-logs.show', $logName))
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("unsafe")</script>', false);
    } finally {
        File::delete($logPath);
    }
});

it('queues backups for app administrators', function () {
    Queue::fake();
    Storage::fake('local');
    $user = User::factory()->create();
    assignRole($user, 'App Administrator');

    $this->actingAs($user, 'backpack')
        ->post(route('admin.backups.store'))
        ->assertRedirect()
        ->assertSessionHas('success');

    Queue::assertPushed(RunApplicationBackup::class);
});
