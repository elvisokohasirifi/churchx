<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMember extends UuidModel
{
    protected $fillable = ['group_id', 'member_id', 'joined_date', 'left_date', 'role', 'is_active'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ChurchGroup::class, 'group_id');
    }

    protected function casts(): array
    {
        return ['joined_date' => 'date', 'left_date' => 'date', 'is_active' => 'boolean'];
    }
}
