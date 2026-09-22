<?php

namespace App\Models;

use App\PledgeStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pledge extends UuidModel
{
    protected $fillable = ['member_id', 'giving_type_id', 'fund_id', 'pledged_amount', 'due_date', 'status', 'notes'];

    protected $appends = ['amount_paid', 'balance'];

    public function payments(): HasMany
    {
        return $this->hasMany(PledgePayment::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function givingType(): BelongsTo
    {
        return $this->belongsTo(GivingType::class);
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function getAmountPaidAttribute(): string
    {
        return bcadd((string) $this->payments()->sum('amount'), '0', 4);
    }

    public function getBalanceAttribute(): string
    {
        return bcsub((string) $this->pledged_amount, $this->amount_paid, 4);
    }

    protected function casts(): array
    {
        return ['pledged_amount' => 'decimal:4', 'due_date' => 'date', 'status' => PledgeStatus::class];
    }
}
