<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\ZoneLeaderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoneLeader extends Model
{
    /** @use HasFactory<ZoneLeaderFactory> */
    use CrudTrait, HasFactory, HasUuid;

    protected $fillable = ['zone_id', 'user_id', 'leadership_title_id', 'start_date', 'end_date', 'is_active'];

    protected $attributes = ['is_active' => true];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leadershipTitle(): BelongsTo
    {
        return $this->belongsTo(LeadershipTitle::class);
    }

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'is_active' => 'boolean'];
    }
}
