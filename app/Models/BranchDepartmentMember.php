<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchDepartmentMember extends UuidModel
{
    protected $fillable = ['branch_department_id', 'member_id', 'department_role_id', 'joined_date', 'left_date', 'is_active'];

    public function branchDepartment(): BelongsTo
    {
        return $this->belongsTo(BranchDepartment::class);
    }

    protected function casts(): array
    {
        return ['joined_date' => 'date', 'left_date' => 'date', 'is_active' => 'boolean'];
    }
}
