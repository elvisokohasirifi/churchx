<?php

namespace App\Http\Controllers\Admin;

use App\ExpenseApprovalDecision;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\PermissionCode;
use App\Services\BranchAccessService;
use App\Services\ExpenseApprovalService;
use App\Services\ExpensePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseApprovalController extends Controller
{
    public function index(BranchAccessService $access): View
    {
        $branchIds = $access->accessibleBranchIds(backpack_user(), PermissionCode::ExpensesApprove);
        abort_if($branchIds->isEmpty() && ! $access->allows(backpack_user(), PermissionCode::ExpensesApprove), 403);

        return view('admin.expenses.approvals', ['expenses' => Expense::query()->whereIn('branch_id', $branchIds)->where('status', 'pending_approval')->latest('date')->paginate(25)]);
    }

    public function decide(Request $request, Expense $expense, ExpenseApprovalService $approvals): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected'], 'comments' => ['nullable', 'string', 'max:2000']]);
        $approvals->decide($expense, backpack_user(), ExpenseApprovalDecision::from($data['decision']), $data['comments'] ?? null);

        return back()->with('success', 'Expense decision recorded.');
    }

    public function pay(Request $request, Expense $expense, ExpensePaymentService $payments): RedirectResponse
    {
        abort_unless(backpack_user()->can(PermissionCode::ExpensesDisburse->value, $expense->branch_id), 403);
        $data = $request->validate(['transaction_reference' => ['nullable', 'string', 'max:255']]);
        $payments->pay($expense, backpack_user(), $data['transaction_reference'] ?? null);

        return back()->with('success', 'Expense marked paid.');
    }
}
