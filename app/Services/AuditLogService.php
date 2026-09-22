<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    public function record(
        string $action,
        ?User $user = null,
        ?Model $auditable = null,
        array $changes = [],
        array $context = [],
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $user?->getKey(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'changes' => $changes ?: null,
            'context' => $context ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
