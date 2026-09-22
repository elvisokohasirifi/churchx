<?php

namespace App\Services;

use App\ExpenseStatus;
use App\Models\Expense;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class ExpensePaymentService
{
    public function __construct(private AuditLogService $audit) {}

    public function pay(Expense $expense, User $user, ?string $reference = null): Expense
    {
        if ($expense->status !== ExpenseStatus::Approved) {
            throw new DomainException('Only approved expenses can be paid.');
        }

        return DB::transaction(function () use ($expense, $user, $reference): Expense {
            $expense->update(['status' => ExpenseStatus::Paid, 'disbursed_by' => $user->id, 'transaction_reference' => $reference ?? $expense->transaction_reference]);
            $this->audit->record('expense.paid', $user, $expense);

            return $expense->refresh();
        });
    }
}
