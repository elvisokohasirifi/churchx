<?php

namespace App\Services;

use App\AccountTransferStatus;
use App\ExpenseStatus;
use App\IncomeStatus;
use App\Models\AccountTransfer;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Income;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FinanceReportService
{
    /** @return array{income:string,expenses:string} */
    public function totals(?string $branchId = null): array
    {
        $income = Income::query()->where('status', IncomeStatus::Completed)->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))->sum('amount');
        $expenses = Expense::query()->where('status', ExpenseStatus::Paid)->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))->sum('amount');

        return ['income' => (string) $income, 'expenses' => (string) $expenses];
    }

    /** @param Collection<int, string> $branchIds
     * @return array{income:string,expenses:string}
     */
    public function totalsForBranches(Collection $branchIds): array
    {
        return [
            'income' => (string) Income::query()->whereIn('branch_id', $branchIds)->where('status', IncomeStatus::Completed)->sum('amount'),
            'expenses' => (string) Expense::query()->whereIn('branch_id', $branchIds)->where('status', ExpenseStatus::Paid)->sum('amount'),
        ];
    }

    public function accountBalance(FinancialAccount $account): string
    {
        $income = Income::query()->where('financial_account_id', $account->id)->where('status', IncomeStatus::Completed)->sum('amount');
        $expenses = Expense::query()->where('financial_account_id', $account->id)->where('status', ExpenseStatus::Paid)->sum('amount');
        $incoming = AccountTransfer::query()->where('to_account_id', $account->id)->where('status', AccountTransferStatus::Completed)->sum('amount');
        $outgoing = AccountTransfer::query()->where('from_account_id', $account->id)->where('status', AccountTransferStatus::Completed)->sum('amount');

        $balance = bcadd((string) $account->opening_balance, (string) $income, 4);
        $balance = bcsub($balance, (string) $expenses, 4);
        $balance = bcadd($balance, (string) $incoming, 4);

        return bcsub($balance, (string) $outgoing, 4);
    }
}
