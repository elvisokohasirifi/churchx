<?php

namespace App\Models;

use App\ExpenseApprovalDecision;

class ExpenseApproval extends UuidModel
{
    protected $fillable = ['expense_id', 'approver_id', 'decision', 'comments', 'decided_at'];

    protected function casts(): array
    {
        return ['decision' => ExpenseApprovalDecision::class, 'decided_at' => 'datetime'];
    }
}
