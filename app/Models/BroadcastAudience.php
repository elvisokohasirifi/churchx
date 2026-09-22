<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastAudience extends UuidModel
{
    protected $fillable = [
        'broadcast_id', 'audience_type', 'audience_id', 'branch_id',
        'filter_field', 'filter_operator', 'filter_value',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
