<?php

namespace App\Policies;

use App\Models\Church;
use App\Models\User;
use App\PermissionCode;
use App\Services\BranchAccessService;

class ChurchPolicy
{
    public function __construct(private BranchAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, PermissionCode::ChurchSettings);
    }

    public function view(User $user, Church $church): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Church $church): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Church $church): bool
    {
        return false;
    }

    public function restore(User $user, Church $church): bool
    {
        return false;
    }

    public function forceDelete(User $user, Church $church): bool
    {
        return false;
    }
}
