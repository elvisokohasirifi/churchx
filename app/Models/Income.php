<?php

namespace App\Models;

use App\IncomeStatus;
use App\Services\ReceiptNumberGenerator;
use DomainException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Income extends UuidModel
{
    use SoftDeletes;

    protected $fillable = ['branch_id', 'service_id', 'giving_type_id', 'fund_id', 'payment_method_id', 'financial_account_id', 'giver_member_id', 'giver_name', 'giver_phone', 'amount', 'currency', 'transaction_reference', 'date', 'notes', 'recorded_by', 'receipt_number', 'status', 'source_type', 'source_id'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function givingType(): BelongsTo
    {
        return $this->belongsTo(GivingType::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'date' => 'date', 'status' => IncomeStatus::class];
    }

    protected static function booted(): void
    {
        static::creating(function (Income $income): void {
            $income->recorded_by ??= backpack_auth()->id() ?? auth()->id();
            $income->receipt_number ??= app(ReceiptNumberGenerator::class)->next();
            $givingType = GivingType::query()->find($income->giving_type_id);
            if ($givingType?->requires_giver && $income->giver_member_id === null && blank($income->giver_name)) {
                throw new DomainException('This giving type requires a member or giver name.');
            }
        });
        static::updating(function (Income $income): void {
            if ($income->getOriginal('status') === IncomeStatus::Completed->value) {
                throw new DomainException('Completed income cannot be edited; create a reversal or correction.');
            }
        });
        static::deleting(function (Income $income): void {
            if ($income->status === IncomeStatus::Completed) {
                throw new DomainException('Completed income cannot be deleted; create a reversal.');
            }
        });
    }
}
