<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Services\ShepherdHierarchyService;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BranchLeader extends Model
{
    use CrudTrait, HasUuid;

    protected $fillable = ['branch_id', 'member_id', 'leadership_title_id', 'start_date', 'end_date', 'is_active'];

    protected static function booted(): void
    {
        static::saved(function (BranchLeader $leader): void {
            $currentLeaders = self::query()
                ->where('branch_id', $leader->branch_id)
                ->where('is_active', true)
                ->whereDate('start_date', '<=', today())
                ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()));

            if ((clone $currentLeaders)->count() === 1 && (clone $currentLeaders)->whereKey($leader->id)->exists()) {
                Member::query()
                    ->whereNull('branch_leader_id')
                    ->whereHas('primaryBranchMembership', fn ($query) => $query->where('branch_id', $leader->branch_id))
                    ->update(['branch_leader_id' => $leader->id]);
            }

            app(ShepherdHierarchyService::class)->syncBranch($leader->branch_id);
        });
    }

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

    public function assignedMembers(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->member?->full_name ?? 'Unknown leader';
    }

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'is_active' => 'boolean'];
    }
}
