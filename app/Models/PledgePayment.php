<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PledgePayment extends UuidModel
{
    protected $fillable = ['pledge_id', 'income_id', 'amount'];

    public function pledge(): BelongsTo
    {
        return $this->belongsTo(Pledge::class);
    }

    public function income(): BelongsTo
    {
        return $this->belongsTo(Income::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:4'];
    }
}
