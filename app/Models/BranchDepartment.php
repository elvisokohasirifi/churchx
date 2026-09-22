<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchDepartment extends UuidModel
{
    protected $fillable = ['branch_id', 'department_id', 'is_active'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
