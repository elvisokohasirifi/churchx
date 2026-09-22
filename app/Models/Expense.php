<?php

namespace App\Models;

use App\ExpenseStatus;
use DomainException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends UuidModel
{
    use SoftDeletes;

    protected $fillable = ['branch_id', 'expense_type_id', 'fund_id', 'payment_method_id', 'financial_account_id', 'requested_by', 'disbursed_by', 'recipient_name', 'recipient_contact', 'amount', 'currency', 'description', 'transaction_reference', 'date', 'receipt', 'notes', 'status'];

    public function approvals(): HasMany
    {
        return $this->hasMany(ExpenseApproval::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function expenseType(): BelongsTo
    {
        return $this->belongsTo(ExpenseType::class);
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

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'date' => 'date', 'status' => ExpenseStatus::class];
    }

    protected static function booted(): void
    {
        static::creating(function (Expense $expense): void {
            $expense->requested_by ??= backpack_auth()->id() ?? auth()->id();
        });
        static::updating(function (Expense $expense): void {
            if ($expense->getOriginal('status') === ExpenseStatus::Paid->value) {
                throw new DomainException('Paid expenses cannot be edited; record a correction.');
            }
        });
        static::deleting(function (Expense $expense): void {
            if ($expense->status === ExpenseStatus::Paid) {
                throw new DomainException('Paid expenses cannot be deleted.');
            }
        });
    }
}
