<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Notifications\UserAccessGrantedNotification;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRole extends Model
{
    use CrudTrait, HasUuid;

    protected $fillable = ['user_id', 'role_id', 'branch_id', 'is_active', 'assigned_by', 'assigned_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'assigned_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (UserRole $assignment): void {
            $assignment->assigned_at ??= now();
            $assignment->assigned_by ??= backpack_auth()->id();
        });

        static::created(function (UserRole $assignment): void {
            $assignment->sendAccessNotification();
        });

        static::updated(function (UserRole $assignment): void {
            if ($assignment->is_active && $assignment->wasChanged(['role_id', 'branch_id', 'is_active'])) {
                $assignment->sendAccessNotification();
            }
        });
    }

    private function sendAccessNotification(): void
    {
        if ($this->is_active && filled($this->user?->email)) {
            $this->user->notify((new UserAccessGrantedNotification($this))->afterCommit());
        }
    }
}
