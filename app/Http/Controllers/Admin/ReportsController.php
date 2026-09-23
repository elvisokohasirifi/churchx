<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSummary;
use App\Models\Branch;
use App\Models\Member;
use App\PermissionCode;
use App\Services\BranchAccessService;
use App\Services\ChurchContext;
use App\Services\FinanceReportService;
use App\Services\MemberAudienceFilter;
use App\Services\OperationalReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public function memberFilter(Request $request, BranchAccessService $access, MemberAudienceFilter $memberFilter): View
    {
        $filterRequested = $request->filled('filter_field')
            || $request->filled('filter_operator')
            || $request->filled('filter_condition');
        $validated = $request->validate([
            'branch_id' => ['nullable', 'uuid'],
            'q' => ['nullable', 'string', 'max:100'],
            'filter_field' => [Rule::requiredIf($filterRequested), 'nullable', 'string', Rule::in(array_keys(MemberAudienceFilter::FIELDS))],
            'filter_operator' => [Rule::requiredIf($filterRequested), 'nullable', 'string', Rule::in(array_keys(MemberAudienceFilter::OPERATORS))],
            'filter_condition' => ['nullable', 'string', 'max:500'],
        ]);
        $branchIds = $access->accessibleBranchIds(backpack_user(), PermissionCode::MembersView);
        abort_if($branchIds->isEmpty() && ! $access->allows(backpack_user(), PermissionCode::MembersView), 403);
        $branchId = $validated['branch_id'] ?? null;
        abort_if($branchId !== null && ! $branchIds->contains($branchId), 403);
        $filterField = $validated['filter_field'] ?? null;
        $filterOperator = $validated['filter_operator'] ?? null;
        $filterCondition = $validated['filter_condition'] ?? null;

        if ($filterOperator !== null && ! MemberAudienceFilter::conditionIsValid($filterOperator, $filterCondition)) {
            $message = in_array($filterOperator, MemberAudienceFilter::RANGE_OPERATORS, true)
                ? 'Enter exactly two comma-separated values.'
                : (in_array($filterOperator, MemberAudienceFilter::LIST_OPERATORS, true)
                    ? 'Enter one or more comma-separated values.'
                    : 'Enter a filter condition.');

            throw ValidationException::withMessages(['filter_condition' => $message]);
        }

        $search = isset($validated['q']) ? trim($validated['q']) : null;
        $query = Member::query()
            ->with([
                'primaryBranchMembership.branch:id,name',
                'branchLeader.member:id,first_name,middle_name,last_name',
                'shepherd:id,first_name,middle_name,last_name',
            ])
            ->whereHas('primaryBranchMembership', fn ($query) => $query
                ->whereIn('branch_id', $branchIds)
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId)))
            ->when($search !== null && $search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('membership_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }));
        $memberFilter->apply($query, $filterField, $filterOperator, $filterCondition);

        return view('admin.reports.member-filter', [
            'members' => $query->orderBy('first_name')->orderBy('last_name')->orderBy('id')->paginate(50)->withQueryString(),
            'branches' => Branch::query()->whereIn('id', $branchIds)->orderBy('name')->get(),
            'branchId' => $branchId,
            'fields' => MemberAudienceFilter::FIELDS,
            'operators' => MemberAudienceFilter::OPERATORS,
            'filterField' => $filterField,
            'filterOperator' => $filterOperator,
            'filterCondition' => $filterCondition,
            'search' => $search,
        ]);
    }

    public function attendanceRecords(Request $request, OperationalReportService $reports, BranchAccessService $access): View
    {
        [$branchIds, $branches, $branchId, $from, $to] = $this->filters($request, $access, PermissionCode::AttendanceView);

        return view('admin.reports.attendance-records', [
            'rows' => $reports->attendanceRecords($branchIds, $from, $to, $branchId, $request->string('q')->trim()->toString() ?: null),
            'branches' => $branches, 'branchId' => $branchId, 'from' => $from, 'to' => $to,
        ]);
    }

    public function attendanceLeaderRanking(Request $request, OperationalReportService $reports, BranchAccessService $access): View
    {
        [$branchIds, $branches, $branchId, $from, $to] = $this->filters($request, $access, PermissionCode::AttendanceView);

        return view('admin.reports.attendance-leader-ranking', [
            'rows' => $reports->attendanceByLeader($branchIds, $from, $to, $branchId),
            'branches' => $branches, 'branchId' => $branchId, 'from' => $from, 'to' => $to,
        ]);
    }

    public function attendanceChurchRanking(Request $request, OperationalReportService $reports, BranchAccessService $access): View
    {
        [$branchIds, $branches, $branchId, $from, $to] = $this->filters($request, $access, PermissionCode::AttendanceView);

        return view('admin.reports.attendance-church-ranking', [
            'rows' => $reports->attendanceByChurch($branchIds, $from, $to, $branchId),
            'branches' => $branches, 'branchId' => $branchId, 'from' => $from, 'to' => $to,
        ]);
    }

    public function givingRecords(Request $request, OperationalReportService $reports, BranchAccessService $access, ChurchContext $church): View
    {
        [$branchIds, $branches, $branchId, $from, $to] = $this->filters($request, $access, PermissionCode::FinancialReportsView);

        return view('admin.reports.giving-records', [
            'rows' => $reports->givingByService($branchIds, $from, $to, $branchId), 'currency' => $church->currency(),
            'branches' => $branches, 'branchId' => $branchId, 'from' => $from, 'to' => $to,
        ]);
    }

    public function givingLeaderRanking(Request $request, OperationalReportService $reports, BranchAccessService $access, ChurchContext $church): View
    {
        [$branchIds, $branches, $branchId, $from, $to] = $this->filters($request, $access, PermissionCode::FinancialReportsView);

        return view('admin.reports.giving-leader-ranking', [
            'rows' => $reports->givingByLeader($branchIds, $from, $to, $branchId), 'currency' => $church->currency(),
            'branches' => $branches, 'branchId' => $branchId, 'from' => $from, 'to' => $to,
        ]);
    }

    public function absenceFollowUp(Request $request, OperationalReportService $reports, BranchAccessService $access): View
    {
        [$branchIds, $branches, $branchId, $from, $to] = $this->filters($request, $access, PermissionCode::AttendanceView);
        $minimumAbsences = (int) $request->integer('minimum_absences', 2);

        return view('admin.reports.absence-follow-up', [
            'rows' => $reports->absenceFollowUp($branchIds, $from, $to, $branchId, $minimumAbsences, $request->string('q')->trim()->toString() ?: null),
            'minimumAbsences' => $minimumAbsences, 'branches' => $branches, 'branchId' => $branchId, 'from' => $from, 'to' => $to,
        ]);
    }

    /** @return array{Collection<int, string>, Collection<int, Branch>, ?string, CarbonImmutable, CarbonImmutable} */
    private function filters(Request $request, BranchAccessService $access, PermissionCode $permission): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'uuid'],
            'q' => ['nullable', 'string', 'max:100'],
            'minimum_absences' => ['nullable', 'integer', 'min:2', 'max:52'],
        ]);
        $branchIds = $access->accessibleBranchIds(backpack_user(), $permission);
        abort_if($branchIds->isEmpty() && ! $access->allows(backpack_user(), $permission), 403);
        $branchId = $validated['branch_id'] ?? null;
        abort_if($branchId !== null && ! $branchIds->contains($branchId), 403);

        return [
            $branchIds,
            Branch::query()->whereIn('id', $branchIds)->orderBy('name')->get(),
            $branchId,
            isset($validated['from']) ? CarbonImmutable::parse($validated['from'])->startOfDay() : CarbonImmutable::today()->subDays(29),
            isset($validated['to']) ? CarbonImmutable::parse($validated['to'])->endOfDay() : CarbonImmutable::today()->endOfDay(),
        ];
    }
}
