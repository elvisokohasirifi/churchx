<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchLeader extends Model
{
    use CrudTrait, HasUuid;

    protected $fillable = ['branch_id', 'member_id', 'leadership_title_id', 'start_date', 'end_date', 'is_active'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
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
