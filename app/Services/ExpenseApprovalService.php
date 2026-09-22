<?php

namespace App\Services;

use App\ExpenseApprovalDecision;
use App\ExpenseStatus;
use App\Models\Expense;
use App\Models\ExpenseApproval;
use App\Models\User;
use App\PermissionCode;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ExpenseApprovalService
{
    public function __construct(private BranchAccessService $access, private AuditLogService $audit) {}

    public function decide(Expense $expense, User $approver, ExpenseApprovalDecision $decision, ?string $comments = null): Expense
    {
        if (! $this->access->allows($approver, PermissionCode::ExpensesApprove, $expense->branch_id)) {
            throw new AuthorizationException;
        }
        if ($expense->requested_by === $approver->id) {
            throw new DomainException('Requesters cannot approve their own expenses.');
        }
        if ($expense->status !== ExpenseStatus::PendingApproval) {
            throw new DomainException('Only pending expenses can be decided.');
        }

        return DB::transaction(function () use ($expense, $approver, $decision, $comments): Expense {
            ExpenseApproval::query()->create(['expense_id' => $expense->id, 'approver_id' => $approver->id, 'decision' => $decision, 'comments' => $comments, 'decided_at' => now()]);
            $expense->update(['status' => $decision === ExpenseApprovalDecision::Approved ? ExpenseStatus::Approved : ExpenseStatus::Rejected]);
            $this->audit->record('expense.'.$decision->value, $approver, $expense);

            return $expense->refresh();
        });
    }
}
