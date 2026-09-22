<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdMember extends Model
{
    use HasUuid;

    protected $fillable = ['household_id', 'member_id', 'relationship_id', 'is_head'];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(HouseholdRelationship::class, 'relationship_id');
    }

    protected function casts(): array
    {
        return ['is_head' => 'boolean'];
    }
}
