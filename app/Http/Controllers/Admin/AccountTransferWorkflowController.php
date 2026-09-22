<?php

namespace App\Http\Controllers\Admin;

use App\AccountTransferStatus;
use App\Http\Controllers\Controller;
use App\Models\AccountTransfer;
use App\Models\FinancialAccount;
use App\PermissionCode;
use App\Services\AccountTransferService;
use App\Services\BranchAccessService;
use App\Services\ChurchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountTransferWorkflowController extends Controller
{
    public function index(BranchAccessService $access, ChurchContext $church): View
    {
        $createBranches = $access->accessibleBranchIds(backpack_user(), PermissionCode::TransfersCreate);
        $approveBranches = $access->accessibleBranchIds(backpack_user(), PermissionCode::TransfersApprove);
        $branchIds = $createBranches->merge($approveBranches)->unique();
        $hasGlobalAccess = $access->allows(backpack_user(), PermissionCode::TransfersCreate) || $access->allows(backpack_user(), PermissionCode::TransfersApprove);
        abort_if($branchIds->isEmpty() && ! $hasGlobalAccess, 403);

        return view('admin.transfers.index', ['transfers' => AccountTransfer::query()->when(! $hasGlobalAccess, fn ($query) => $query->whereHas('fromAccount', fn ($account) => $account->whereIn('branch_id', $branchIds))->whereHas('toAccount', fn ($account) => $account->whereIn('branch_id', $branchIds)))->latest('date')->paginate(25), 'accounts' => FinancialAccount::query()->when(! $hasGlobalAccess, fn ($query) => $query->whereIn('branch_id', $branchIds))->where('is_active', true)->orderBy('name')->get(), 'currency' => $church->currency()]);
    }

    public function store(Request $request, BranchAccessService $access): RedirectResponse
    {
        $data = $request->validate(['from_account_id' => ['required', 'different:to_account_id', 'exists:financial_accounts,id'], 'to_account_id' => ['required', 'exists:financial_accounts,id'], 'amount' => ['required', 'decimal:0,4', 'gt:0'], 'currency' => ['required', 'size:3'], 'date' => ['required', 'date'], 'transaction_reference' => ['nullable', 'string'], 'notes' => ['nullable', 'string']]);
        $accounts = FinancialAccount::query()->whereKey([$data['from_account_id'], $data['to_account_id']])->get();
        abort_if($accounts->count() !== 2 || $accounts->contains(fn (FinancialAccount $account): bool => ! $access->allows(backpack_user(), PermissionCode::TransfersCreate, $account->branch_id)), 403);
        abort_if($accounts->contains(fn (FinancialAccount $account): bool => $account->currency !== strtoupper($data['currency'])), 422, 'Currency must match both accounts.');
        AccountTransfer::query()->create([...$data, 'currency' => strtoupper($data['currency']), 'initiated_by' => backpack_user()->id, 'status' => AccountTransferStatus::PendingApproval]);

        return back()->with('success', 'Transfer submitted for approval.');
    }

    public function approve(AccountTransfer $transfer, AccountTransferService $service): RedirectResponse
    {
        $service->approve($transfer, backpack_user());

        return back()->with('success', 'Transfer approved.');
    }

    public function complete(AccountTransfer $transfer, AccountTransferService $service): RedirectResponse
    {
        $service->complete($transfer, backpack_user());

        return back()->with('success', 'Transfer completed.');
    }
}
