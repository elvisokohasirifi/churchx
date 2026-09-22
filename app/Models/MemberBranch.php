<?php

namespace App\Models;

use App\MemberBranchStatus;
use App\Models\Concerns\HasUuid;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberBranch extends Model
{
    use CrudTrait, HasUuid;

    protected $fillable = ['member_id', 'branch_id', 'joined_date', 'left_date', 'is_primary', 'status'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected function casts(): array
    {
        return [
            'joined_date' => 'date',
            'left_date' => 'date',
            'is_primary' => 'boolean',
            'status' => MemberBranchStatus::class,
        ];
    }
}
