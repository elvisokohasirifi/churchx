<?php

namespace App\Models;

use App\FinancialAccountType;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialAccount extends UuidModel
{
    use SoftDeletes;

    protected $fillable = ['branch_id', 'name', 'type', 'currency', 'account_number', 'bank_name', 'phone_number', 'opening_balance', 'is_active'];

    protected function casts(): array
    {
        return ['type' => FinancialAccountType::class, 'opening_balance' => 'decimal:4', 'is_active' => 'boolean'];
    }
}
