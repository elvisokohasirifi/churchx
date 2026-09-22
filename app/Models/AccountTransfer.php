<?php

namespace App\Models;

use App\AccountTransferStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountTransfer extends UuidModel
{
    protected $fillable = ['from_account_id', 'to_account_id', 'amount', 'currency', 'transaction_reference', 'date', 'initiated_by', 'approved_by', 'approved_at', 'notes', 'status'];

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'to_account_id');
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'date' => 'date', 'approved_at' => 'datetime', 'status' => AccountTransferStatus::class];
    }
}
