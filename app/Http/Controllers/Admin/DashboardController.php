<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSummary;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Member;
use App\Models\Visitor;
use App\PermissionCode;
use App\Services\BranchAccessService;
use App\Services\FinanceReportService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(FinanceReportService $finance, BranchAccessService $access): View
    {
        $memberBranchIds = $access->accessibleBranchIds(backpack_user(), PermissionCode::MembersView);
        $visitorBranchIds = $access->accessibleBranchIds(backpack_user(), PermissionCode::VisitorsView);
        $branchIds = $access->accessibleBranchIds(backpack_user(), PermissionCode::BranchesView);
        $attendanceBranchIds = $access->accessibleBranchIds(backpack_user(), PermissionCode::AttendanceView);
        $financeBranchIds = $access->accessibleBranchIds(backpack_user(), PermissionCode::FinancialReportsView);
        $canViewMembers = $access->allows(backpack_user(), PermissionCode::MembersView) || $memberBranchIds->isNotEmpty();
        $canViewVisitors = $access->allows(backpack_user(), PermissionCode::VisitorsView) || $visitorBranchIds->isNotEmpty();
        $canViewBranches = $access->allows(backpack_user(), PermissionCode::BranchesView) || $branchIds->isNotEmpty();
        $hasGlobalFinanceAccess = $access->allows(backpack_user(), PermissionCode::FinancialReportsView);
        $canViewFinance = $hasGlobalFinanceAccess || $financeBranchIds->isNotEmpty();

        $recentIncomes = collect();
        $recentExpenses = collect();
        $financeTotals = null;

        if ($canViewFinance) {
            $financeTotals = $hasGlobalFinanceAccess
                ? $finance->totals()
                : $finance->totalsForBranches($financeBranchIds);
            $recentIncomes = Income::query()
                ->with(['branch:id,name', 'givingType:id,name'])
                ->when(! $hasGlobalFinanceAccess, fn ($query) => $query->whereIn('branch_id', $financeBranchIds))
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(20)
                ->get();
            $recentExpenses = Expense::query()
                ->with(['branch:id,name', 'expenseType:id,name'])
                ->when(! $hasGlobalFinanceAccess, fn ($query) => $query->whereIn('branch_id', $financeBranchIds))
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }

        return view('admin.dashboard', [
            'operationalCards' => collect([
                $canViewMembers ? ['Members', Member::query()->whereHas('primaryBranchMembership', fn ($query) => $query->whereIn('branch_id', $memberBranchIds))->count(), 'bg-primary'] : null,
                $canViewVisitors ? ['Visitors', Visitor::query()->whereIn('branch_id', $visitorBranchIds)->count(), 'bg-success'] : null,
                $canViewBranches ? ['Branches', Branch::query()->whereIn('id', $branchIds)->count(), 'bg-warning'] : null,
            ])->filter()->values(),
            'latestAttendance' => AttendanceSummary::query()->whereIn('branch_id', $attendanceBranchIds)->latest()->first(),
            'finance' => $financeTotals,
            'recentIncomes' => $recentIncomes,
            'recentExpenses' => $recentExpenses,
        ]);
    }
}
