<?php

namespace App\Models;

use App\ServiceScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends UuidModel
{
    protected $fillable = ['branch_id', 'scope', 'name', 'service_type', 'date', 'start_time', 'end_time', 'location', 'status'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function attendanceSummaries(): HasMany
    {
        return $this->hasMany(AttendanceSummary::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'service_branches');
    }

    public function memberAttendances(): HasMany
    {
        return $this->hasMany(MemberAttendance::class);
    }

    public function visitorAttendances(): HasMany
    {
        return $this->hasMany(VisitorAttendance::class);
    }

    public function offeringCollections(): HasMany
    {
        return $this->hasMany(OfferingCollection::class);
    }

    protected function casts(): array
    {
        return ['scope' => ServiceScope::class, 'date' => 'date'];
    }
}
