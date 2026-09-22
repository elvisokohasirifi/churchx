<?php

use App\Models\Branch;
use App\Models\User;
use App\PermissionCode;
use App\Services\BranchAccessService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('limits a branch role to its assigned branch', function () {
    $assignedBranch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $user = User::factory()->create();
    assignRole($user, 'Branch Administrator', $assignedBranch);
    $access = app(BranchAccessService::class);

    expect($access->allows($user, PermissionCode::BranchesView, $assignedBranch))->toBeTrue()
        ->and($access->allows($user, PermissionCode::BranchesView, $otherBranch))->toBeFalse();
});

it('allows a church-wide role to access every branch in its permissions', function () {
    $firstBranch = Branch::factory()->create();
    $secondBranch = Branch::factory()->create();
    $user = User::factory()->create();
    assignRole($user, 'Church Administrator');
    $access = app(BranchAccessService::class);

    expect($access->allows($user, PermissionCode::BranchesView, $firstBranch))->toBeTrue()
        ->and($access->allows($user, PermissionCode::BranchesView, $secondBranch))->toBeTrue();
});

it('creates UUID primary keys for phase one entities', function () {
    $user = User::factory()->create();
    $branch = Branch::factory()->create();

    expect(Str::isUuid($user->id))->toBeTrue()
        ->and(Str::isUuid($branch->id))->toBeTrue();
});
