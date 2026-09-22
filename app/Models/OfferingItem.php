<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferingItem extends UuidModel
{
    protected $fillable = ['offering_collection_id', 'giving_type_id', 'fund_id', 'payment_method_id', 'financial_account_id', 'member_id', 'giver_name', 'giver_phone', 'amount', 'currency', 'income_id'];

    public function givingType(): BelongsTo
    {
        return $this->belongsTo(GivingType::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(OfferingCollection::class, 'offering_collection_id');
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
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
