<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends UuidModel
{
    use SoftDeletes;

    protected $fillable = ['branch_id', 'name', 'asset_type_id', 'purchase_date', 'purchase_price', 'currency', 'condition', 'serial_number', 'quantity', 'status', 'notes'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    protected function casts(): array
    {
        return ['purchase_date' => 'date', 'purchase_price' => 'decimal:4'];
    }
}
