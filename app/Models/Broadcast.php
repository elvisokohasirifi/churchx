<?php

namespace App\Models;

use App\BroadcastChannel;
use App\BroadcastStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Broadcast extends UuidModel
{
    protected $fillable = ['title', 'message', 'channel', 'created_by', 'scheduled_at', 'sent_at', 'status'];

    public function audiences(): HasMany
    {
        return $this->hasMany(BroadcastAudience::class);
    }

    protected function casts(): array
    {
        return ['channel' => BroadcastChannel::class, 'status' => BroadcastStatus::class, 'scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
