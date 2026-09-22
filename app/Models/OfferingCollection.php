<?php

namespace App\Models;

use App\OfferingCollectionStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfferingCollection extends UuidModel
{
    protected $fillable = ['service_id', 'branch_id', 'date', 'counted_by', 'verified_by', 'verified_at', 'status', 'notes'];

    public function items(): HasMany
    {
        return $this->hasMany(OfferingItem::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected function casts(): array
    {
        return ['date' => 'date', 'verified_at' => 'datetime', 'status' => OfferingCollectionStatus::class];
    }
}
