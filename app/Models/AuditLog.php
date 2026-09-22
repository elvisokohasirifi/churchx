<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use CrudTrait, HasUuid;

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'auditable_type', 'auditable_id', 'changes', 'context', 'ip_address', 'user_agent',
        'created_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return ['changes' => 'array', 'context' => 'array', 'created_at' => 'datetime'];
    }
}
