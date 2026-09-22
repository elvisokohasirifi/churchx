<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchGroup extends UuidModel
{
    protected $fillable = ['name', 'type', 'branch_id', 'description'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
