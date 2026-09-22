<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSummary;
use App\Models\Branch;
use App\Models\Member;
use App\PermissionCode;
use App\Services\BranchAccessService;
use App\Services\FinanceReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function finance(Request $request, FinanceReportService $reports, BranchAccessService $access): View
    {
        $branchIds = $access->accessibleBranchIds(backpack_user(), PermissionCode::FinancialReportsView);
        $hasGlobalAccess = $access->allows(backpack_user(), PermissionCode::FinancialReportsView);
        abort_if($branchIds->isEmpty() && ! $hasGlobalAccess, 403);
        $branchId = $request->string('branch_id')->toString() ?: null;
        if ($branchId !== null) {
            abort_unless($access->allows(backpack_user(), PermissionCode::FinancialReportsView, $branchId), 403);
        }

        $totals = $branchId !== null || $hasGlobalAccess ? $reports->totals($branchId) : $reports->totalsForBranches($branchIds);

        return view('admin.reports.finance', ['totals' => $totals, 'branches' => Branch::query()->whereIn('id', $branchIds)->get(), 'branchId' => $branchId]);
    }

    public function attendance(BranchAccessService $access): View
    {
        $ids = $access->accessibleBranchIds(backpack_user(), PermissionCode::AttendanceView);

        return view('admin.reports.attendance', ['summaries' => AttendanceSummary::query()->whereIn('branch_id', $ids)->latest()->paginate(30)]);
    }

    public function members(BranchAccessService $access): View
    {
        $ids = $access->accessibleBranchIds(backpack_user(), PermissionCode::MembersView);

        return view('admin.reports.members', ['counts' => Member::query()->selectRaw('membership_status, count(*) as total')->whereHas('branchHistory', fn ($query) => $query->whereIn('branch_id', $ids)->where('is_primary', true)->whereNull('left_date'))->groupBy('membership_status')->pluck('total', 'membership_status')]);
    }
}
