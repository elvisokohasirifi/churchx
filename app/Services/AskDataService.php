<?php

namespace App\Services;

use App\ExpenseStatus;
use App\IncomeStatus;
use App\MemberBranchStatus;
use App\Models\AttendanceSummary;
use App\Models\Branch;
use App\Models\BranchLeader;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Member;
use App\Models\MemberBranch;
use App\Models\User;
use App\PermissionCode;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AskDataService
{
    public function __construct(
        private readonly BranchAccessService $branchAccess,
        private readonly ChurchContext $churchContext,
    ) {}

    public function canUse(User $user): bool
    {
        return $this->hasAccess($user, PermissionCode::AttendanceView)
            || $this->hasAccess($user, PermissionCode::MembersView)
            || $this->hasAccess($user, PermissionCode::FinancialReportsView);
    }

    /** @return list<array{label:string, question:string}> */
    public function capabilities(User $user): array
    {
        return collect([
            $this->hasAccess($user, PermissionCode::AttendanceView) ? ['label' => 'Attendance by branch', 'question' => 'Compare attendance by church branch this year'] : null,
            $this->hasAccess($user, PermissionCode::AttendanceView) ? ['label' => 'Attendance by leader', 'question' => 'Compare attendance by church leaders this year'] : null,
            $this->hasAccess($user, PermissionCode::MembersView) ? ['label' => 'Members by branch', 'question' => 'Show the number of members in each church branch'] : null,
            $this->hasAccess($user, PermissionCode::MembersView) ? ['label' => 'Membership status', 'question' => 'Break down members by membership status'] : null,
            $this->hasAccess($user, PermissionCode::FinancialReportsView) ? ['label' => 'Income vs expenses', 'question' => 'Compare monthly income and expenses this year'] : null,
            $this->hasAccess($user, PermissionCode::FinancialReportsView) ? ['label' => 'Finance by branch', 'question' => 'Compare finances by church branch this year'] : null,
        ])->filter()->values()->all();
    }

    /**
     * @return array{
     *     title:string,
     *     answer:string,
     *     period:string,
     *     chart:array{type:string, labels:list<string>, datasets:list<array{label:string, data:list<int|float>, color:string}>},
     *     columns:list<array{key:string, label:string, numeric?:bool}>,
     *     rows:list<array<string, int|float|string>>
     * }
     */
    public function analyze(User $user, string $question, ?string $from = null, ?string $to = null): array
    {
        $normalizedQuestion = Str::of($question)->lower()->squish()->toString();
        [$fromDate, $toDate] = $this->resolveDates($normalizedQuestion, $from, $to);
        $mentionsFinance = Str::contains($normalizedQuestion, ['finance', 'financial', 'income', 'expense', 'giving', 'offering', 'tithe', 'revenue', 'fund']);
        $mentionsAttendance = Str::contains($normalizedQuestion, ['attendance', 'attending', 'service', 'services', 'church leader', 'church leaders']);
        $mentionsMembers = Str::contains($normalizedQuestion, ['member', 'members', 'membership', 'people', 'congregation']);

        if ($mentionsFinance) {
            $this->authorizeDomain($user, PermissionCode::FinancialReportsView, 'finance');

            if (Str::contains($normalizedQuestion, ['expense type', 'expense category', 'expenses by type', 'expenses by category'])) {
                return $this->expensesByType($user, $fromDate, $toDate);
            }

            if (Str::contains($normalizedQuestion, ['giving type', 'income type', 'income category', 'giving category', 'offerings by'])) {
                return $this->incomeByGivingType($user, $fromDate, $toDate);
            }

            if (Str::contains($normalizedQuestion, ['branch', 'branches', 'church', 'churches', 'location', 'locations'])) {
                return $this->financeByBranch($user, $fromDate, $toDate);
            }

            return $this->monthlyFinance($user, $fromDate, $toDate);
        }

        if ($mentionsAttendance) {
            $this->authorizeDomain($user, PermissionCode::AttendanceView, 'attendance');

            if (Str::contains($normalizedQuestion, ['leader', 'leaders', 'pastor', 'pastors', 'overseer', 'overseers'])) {
                return $this->attendanceByLeader($user, $fromDate, $toDate);
            }

            if (Str::contains($normalizedQuestion, ['branch', 'branches', 'church', 'churches', 'location', 'locations', 'compare'])) {
                return $this->attendanceByBranch($user, $fromDate, $toDate);
            }

            return $this->attendanceTrend($user, $fromDate, $toDate);
        }

        if ($mentionsMembers) {
            $this->authorizeDomain($user, PermissionCode::MembersView, 'member');

            if (Str::contains($normalizedQuestion, ['branch', 'branches', 'church', 'churches', 'location', 'locations', 'compare'])) {
                return $this->membersByBranch($user);
            }

            return $this->membersByStatus($user);
        }

        if ($this->hasAccess($user, PermissionCode::AttendanceView)) {
            return $this->attendanceTrend($user, $fromDate, $toDate);
        }

        if ($this->hasAccess($user, PermissionCode::MembersView)) {
            return $this->membersByStatus($user);
        }

        $this->authorizeDomain($user, PermissionCode::FinancialReportsView, 'finance');

        return $this->monthlyFinance($user, $fromDate, $toDate);
    }

    /** @return array{0:CarbonImmutable, 1:CarbonImmutable} */
    private function resolveDates(string $question, ?string $from, ?string $to): array
    {
        $today = CarbonImmutable::today();

        if ($from !== null || $to !== null) {
            $toDate = $to !== null ? CarbonImmutable::parse($to) : $today;
            $fromDate = $from !== null ? CarbonImmutable::parse($from) : $toDate->subMonths(11)->startOfMonth();

            return [$fromDate, $toDate];
        }

        if (Str::contains($question, ['this month', 'current month'])) {
            return [$today->startOfMonth(), $today];
        }

        if (Str::contains($question, ['last month', 'previous month'])) {
            $lastMonth = $today->subMonth();

            return [$lastMonth->startOfMonth(), $lastMonth->endOfMonth()];
        }

        if (Str::contains($question, ['this year', 'current year'])) {
            return [$today->startOfYear(), $today];
        }

        if (Str::contains($question, ['last 30 days', 'past 30 days'])) {
            return [$today->subDays(29), $today];
        }

        return [$today->subMonths(11)->startOfMonth(), $today];
    }

    /** @return array<string, mixed> */
    private function attendanceTrend(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $branchIds = $this->branchIds($user, PermissionCode::AttendanceView);
        $periodExpression = $this->monthExpression('services.date');
        $records = AttendanceSummary::query()
            ->join('services', 'services.id', '=', 'attendance_summaries.service_id')
            ->whereIn('attendance_summaries.branch_id', $branchIds)
            ->whereBetween('services.date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw($periodExpression.' as period')
            ->selectRaw('SUM(total_male + total_female + total_children) as attendance')
            ->selectRaw('SUM(total_members) as members')
            ->selectRaw('SUM(total_visitors) as visitors')
            ->selectRaw('COUNT(DISTINCT attendance_summaries.service_id) as services')
            ->groupByRaw($periodExpression)
            ->orderBy('period')
            ->get();
        $rows = $records->map(fn ($record): array => [
            'period' => $record->period,
            'services' => (int) $record->services,
            'attendance' => (int) $record->attendance,
            'members' => (int) $record->members,
            'visitors' => (int) $record->visitors,
        ])->all();
        $total = collect($rows)->sum('attendance');

        return $this->result(
            'Attendance trend',
            $total === 0 ? 'No attendance has been recorded for the selected period.' : number_format($total).' attendances were recorded across '.number_format(collect($rows)->sum('services')).' services in the selected period.',
            $from,
            $to,
            'line',
            collect($rows)->pluck('period')->all(),
            [
                $this->dataset('Total attendance', collect($rows)->pluck('attendance')->all(), '#206bc4'),
                $this->dataset('Members', collect($rows)->pluck('members')->all(), '#2fb344'),
                $this->dataset('Visitors', collect($rows)->pluck('visitors')->all(), '#f59f00'),
            ],
            [
                ['key' => 'period', 'label' => 'Month'],
                ['key' => 'services', 'label' => 'Services', 'numeric' => true],
                ['key' => 'attendance', 'label' => 'Attendance', 'numeric' => true],
                ['key' => 'members', 'label' => 'Members', 'numeric' => true],
                ['key' => 'visitors', 'label' => 'Visitors', 'numeric' => true],
            ],
            $rows,
        );
    }

    /** @return array<string, mixed> */
    private function attendanceByBranch(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $branchIds = $this->branchIds($user, PermissionCode::AttendanceView);
        $rows = AttendanceSummary::query()
            ->join('services', 'services.id', '=', 'attendance_summaries.service_id')
            ->join('branches', 'branches.id', '=', 'attendance_summaries.branch_id')
            ->whereIn('attendance_summaries.branch_id', $branchIds)
            ->whereNull('branches.deleted_at')
            ->whereBetween('services.date', [$from->toDateString(), $to->toDateString()])
            ->select(['branches.id', 'branches.name'])
            ->selectRaw('COUNT(DISTINCT attendance_summaries.service_id) as services')
            ->selectRaw('SUM(total_male + total_female + total_children) as attendance')
            ->selectRaw('ROUND(AVG(total_male + total_female + total_children), 1) as average')
            ->groupBy('branches.id', 'branches.name')
            ->orderByDesc('attendance')
            ->get()
            ->map(fn ($record): array => [
                'branch' => $record->name,
                'services' => (int) $record->services,
                'attendance' => (int) $record->attendance,
                'average' => (float) $record->average,
            ])->all();

        return $this->result(
            'Attendance by church branch',
            $rows === [] ? 'No branch attendance has been recorded for the selected period.' : 'The comparison ranks '.count($rows).' church branches by total recorded attendance.',
            $from,
            $to,
            'bar',
            collect($rows)->pluck('branch')->all(),
            [$this->dataset('Attendance', collect($rows)->pluck('attendance')->all(), '#206bc4')],
            [
                ['key' => 'branch', 'label' => 'Church branch'],
                ['key' => 'services', 'label' => 'Services', 'numeric' => true],
                ['key' => 'attendance', 'label' => 'Attendance', 'numeric' => true],
                ['key' => 'average', 'label' => 'Average / service', 'numeric' => true],
            ],
            $rows,
        );
    }

    /** @return array<string, mixed> */
    private function attendanceByLeader(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $branchIds = $this->branchIds($user, PermissionCode::AttendanceView);
        $attendanceByBranch = AttendanceSummary::query()
            ->join('services', 'services.id', '=', 'attendance_summaries.service_id')
            ->whereIn('attendance_summaries.branch_id', $branchIds)
            ->whereBetween('services.date', [$from->toDateString(), $to->toDateString()])
            ->select('attendance_summaries.branch_id')
            ->selectRaw('COUNT(DISTINCT attendance_summaries.service_id) as services')
            ->selectRaw('SUM(total_male + total_female + total_children) as attendance')
            ->selectRaw('ROUND(AVG(total_male + total_female + total_children), 1) as average')
            ->groupBy('attendance_summaries.branch_id')
            ->get()
            ->keyBy('branch_id');
        $rows = BranchLeader::query()
            ->with(['branch:id,name', 'member:id,first_name,middle_name,last_name', 'leadershipTitle:id,name'])
            ->whereIn('branch_id', $branchIds)
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('end_date')->orWhere('end_date', '>=', today());
            })
            ->get()
            ->map(function (BranchLeader $leader) use ($attendanceByBranch): array {
                $attendance = $attendanceByBranch->get($leader->branch_id);

                return [
                    'leader' => $leader->member->full_name,
                    'title' => $leader->leadershipTitle->name,
                    'branch' => $leader->branch->name,
                    'services' => (int) ($attendance?->services ?? 0),
                    'attendance' => (int) ($attendance?->attendance ?? 0),
                    'average' => (float) ($attendance?->average ?? 0),
                ];
            })
            ->sortByDesc('attendance')
            ->values()
            ->all();

        return $this->result(
            'Attendance by church leader',
            $rows === [] ? 'No active branch leaders are available for this comparison.' : 'Attendance is compared using the branch currently assigned to each active church leader.',
            $from,
            $to,
            'bar',
            collect($rows)->map(fn (array $row): string => $row['leader'].' — '.$row['branch'])->all(),
            [$this->dataset('Attendance', collect($rows)->pluck('attendance')->all(), '#6f42c1')],
            [
                ['key' => 'leader', 'label' => 'Church leader'],
                ['key' => 'title', 'label' => 'Title'],
                ['key' => 'branch', 'label' => 'Branch'],
                ['key' => 'services', 'label' => 'Services', 'numeric' => true],
                ['key' => 'attendance', 'label' => 'Attendance', 'numeric' => true],
                ['key' => 'average', 'label' => 'Average / service', 'numeric' => true],
            ],
            $rows,
        );
    }

    /** @return array<string, mixed> */
    private function membersByBranch(User $user): array
    {
        $branchIds = $this->branchIds($user, PermissionCode::MembersView);
        $rows = MemberBranch::query()
            ->join('branches', 'branches.id', '=', 'member_branches.branch_id')
            ->join('members', 'members.id', '=', 'member_branches.member_id')
            ->whereIn('member_branches.branch_id', $branchIds)
            ->where('member_branches.is_primary', true)
            ->whereNull('member_branches.left_date')
            ->where('member_branches.status', MemberBranchStatus::Active->value)
            ->whereNull('branches.deleted_at')
            ->whereNull('members.deleted_at')
            ->select(['branches.id', 'branches.name'])
            ->selectRaw('COUNT(member_branches.member_id) as members')
            ->groupBy('branches.id', 'branches.name')
            ->orderByDesc('members')
            ->get()
            ->map(fn ($record): array => ['branch' => $record->name, 'members' => (int) $record->members])
            ->all();
        $today = CarbonImmutable::today();

        return $this->result(
            'Members by church branch',
            $rows === [] ? 'No active primary branch memberships were found.' : number_format(collect($rows)->sum('members')).' active members are assigned across '.count($rows).' church branches.',
            $today,
            $today,
            'bar',
            collect($rows)->pluck('branch')->all(),
            [$this->dataset('Members', collect($rows)->pluck('members')->all(), '#2fb344')],
            [['key' => 'branch', 'label' => 'Church branch'], ['key' => 'members', 'label' => 'Members', 'numeric' => true]],
            $rows,
            'Current membership',
        );
    }

    /** @return array<string, mixed> */
    private function membersByStatus(User $user): array
    {
        $branchIds = $this->branchIds($user, PermissionCode::MembersView);
        $rows = Member::query()
            ->join('member_branches', 'member_branches.member_id', '=', 'members.id')
            ->whereIn('member_branches.branch_id', $branchIds)
            ->where('member_branches.is_primary', true)
            ->whereNull('member_branches.left_date')
            ->whereNull('members.deleted_at')
            ->select('members.membership_status')
            ->selectRaw('COUNT(members.id) as members')
            ->groupBy('members.membership_status')
            ->orderByDesc('members')
            ->get()
            ->map(fn ($record): array => [
                'status' => Str::headline($record->membership_status->value),
                'members' => (int) $record->members,
            ])
            ->all();
        $today = CarbonImmutable::today();

        return $this->result(
            'Members by membership status',
            $rows === [] ? 'No active branch memberships were found.' : 'The table and chart show the current membership-status distribution for '.number_format(collect($rows)->sum('members')).' people.',
            $today,
            $today,
            'bar',
            collect($rows)->pluck('status')->all(),
            [$this->dataset('Members', collect($rows)->pluck('members')->all(), '#2fb344')],
            [['key' => 'status', 'label' => 'Membership status'], ['key' => 'members', 'label' => 'Members', 'numeric' => true]],
            $rows,
            'Current membership',
        );
    }

    /** @return array<string, mixed> */
    private function monthlyFinance(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $branchIds = $this->branchIds($user, PermissionCode::FinancialReportsView);
        $income = $this->monthlyAmounts(Income::query(), 'incomes.date', 'incomes.branch_id', $branchIds, 'incomes.status', IncomeStatus::Completed->value, $from, $to);
        $expenses = $this->monthlyAmounts(Expense::query(), 'expenses.date', 'expenses.branch_id', $branchIds, 'expenses.status', ExpenseStatus::Paid->value, $from, $to);
        $periods = $this->monthPeriods($from, $to);
        $rows = collect($periods)->map(fn (string $period): array => [
            'period' => $period,
            'income' => (float) ($income->get($period) ?? 0),
            'expenses' => (float) ($expenses->get($period) ?? 0),
            'net' => (float) ($income->get($period) ?? 0) - (float) ($expenses->get($period) ?? 0),
        ])->all();
        $currency = $this->churchContext->currency();
        $totalIncome = collect($rows)->sum('income');
        $totalExpenses = collect($rows)->sum('expenses');

        return $this->result(
            'Income and expenses over time',
            $currency.' '.number_format($totalIncome, 2).' in completed income and '.$currency.' '.number_format($totalExpenses, 2).' in paid expenses were recorded, leaving a net of '.$currency.' '.number_format($totalIncome - $totalExpenses, 2).'.',
            $from,
            $to,
            'line',
            $periods,
            [
                $this->dataset('Completed income', collect($rows)->pluck('income')->all(), '#2fb344'),
                $this->dataset('Paid expenses', collect($rows)->pluck('expenses')->all(), '#d63939'),
                $this->dataset('Net', collect($rows)->pluck('net')->all(), '#206bc4'),
            ],
            [
                ['key' => 'period', 'label' => 'Month'],
                ['key' => 'income', 'label' => 'Income ('.$currency.')', 'numeric' => true],
                ['key' => 'expenses', 'label' => 'Expenses ('.$currency.')', 'numeric' => true],
                ['key' => 'net', 'label' => 'Net ('.$currency.')', 'numeric' => true],
            ],
            $rows,
        );
    }

    /** @return array<string, mixed> */
    private function financeByBranch(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $branchIds = $this->branchIds($user, PermissionCode::FinancialReportsView);
        $income = Income::query()->whereIn('branch_id', $branchIds)->where('status', IncomeStatus::Completed->value)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])->select('branch_id')->selectRaw('SUM(amount) as total')->groupBy('branch_id')->pluck('total', 'branch_id');
        $expenses = Expense::query()->whereIn('branch_id', $branchIds)->where('status', ExpenseStatus::Paid->value)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])->select('branch_id')->selectRaw('SUM(amount) as total')->groupBy('branch_id')->pluck('total', 'branch_id');
        $rows = Branch::query()->whereIn('id', $branchIds)->orderBy('name')->get(['id', 'name'])
            ->map(fn (Branch $branch): array => [
                'branch' => $branch->name,
                'income' => (float) ($income->get($branch->id) ?? 0),
                'expenses' => (float) ($expenses->get($branch->id) ?? 0),
                'net' => (float) ($income->get($branch->id) ?? 0) - (float) ($expenses->get($branch->id) ?? 0),
            ])->all();
        $currency = $this->churchContext->currency();

        return $this->result(
            'Finance by church branch',
            $rows === [] ? 'No church branches are available for this comparison.' : 'Completed income and paid expenses are compared for '.count($rows).' church branches.',
            $from,
            $to,
            'bar',
            collect($rows)->pluck('branch')->all(),
            [$this->dataset('Completed income', collect($rows)->pluck('income')->all(), '#2fb344'), $this->dataset('Paid expenses', collect($rows)->pluck('expenses')->all(), '#d63939')],
            [
                ['key' => 'branch', 'label' => 'Church branch'],
                ['key' => 'income', 'label' => 'Income ('.$currency.')', 'numeric' => true],
                ['key' => 'expenses', 'label' => 'Expenses ('.$currency.')', 'numeric' => true],
                ['key' => 'net', 'label' => 'Net ('.$currency.')', 'numeric' => true],
            ],
            $rows,
        );
    }

    /** @return array<string, mixed> */
    private function incomeByGivingType(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $branchIds = $this->branchIds($user, PermissionCode::FinancialReportsView);
        $rows = Income::query()->join('giving_types', 'giving_types.id', '=', 'incomes.giving_type_id')
            ->whereIn('incomes.branch_id', $branchIds)->where('incomes.status', IncomeStatus::Completed->value)
            ->whereBetween('incomes.date', [$from->toDateString(), $to->toDateString()])
            ->select(['giving_types.id', 'giving_types.name'])->selectRaw('SUM(incomes.amount) as amount')
            ->groupBy('giving_types.id', 'giving_types.name')->orderByDesc('amount')->get()
            ->map(fn ($record): array => ['type' => $record->name, 'amount' => (float) $record->amount])->all();
        $currency = $this->churchContext->currency();

        return $this->result(
            'Income by giving type',
            $rows === [] ? 'No completed income was recorded for the selected period.' : 'Completed income is grouped across '.count($rows).' giving types.',
            $from,
            $to,
            'bar',
            collect($rows)->pluck('type')->all(),
            [$this->dataset('Income', collect($rows)->pluck('amount')->all(), '#2fb344')],
            [['key' => 'type', 'label' => 'Giving type'], ['key' => 'amount', 'label' => 'Amount ('.$currency.')', 'numeric' => true]],
            $rows,
        );
    }

    /** @return array<string, mixed> */
    private function expensesByType(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $branchIds = $this->branchIds($user, PermissionCode::FinancialReportsView);
        $rows = Expense::query()->join('expense_types', 'expense_types.id', '=', 'expenses.expense_type_id')
            ->whereIn('expenses.branch_id', $branchIds)->where('expenses.status', ExpenseStatus::Paid->value)
            ->whereBetween('expenses.date', [$from->toDateString(), $to->toDateString()])
            ->select(['expense_types.id', 'expense_types.name'])->selectRaw('SUM(expenses.amount) as amount')
            ->groupBy('expense_types.id', 'expense_types.name')->orderByDesc('amount')->get()
            ->map(fn ($record): array => ['type' => $record->name, 'amount' => (float) $record->amount])->all();
        $currency = $this->churchContext->currency();

        return $this->result(
            'Expenses by type',
            $rows === [] ? 'No paid expenses were recorded for the selected period.' : 'Paid expenses are grouped across '.count($rows).' expense types.',
            $from,
            $to,
            'bar',
            collect($rows)->pluck('type')->all(),
            [$this->dataset('Expenses', collect($rows)->pluck('amount')->all(), '#d63939')],
            [['key' => 'type', 'label' => 'Expense type'], ['key' => 'amount', 'label' => 'Amount ('.$currency.')', 'numeric' => true]],
            $rows,
        );
    }

    /**
     * @param  Builder<Income>|Builder<Expense>  $query
     * @param  Collection<int, string>  $branchIds
     * @return Collection<string, string|int|float>
     */
    private function monthlyAmounts(Builder $query, string $dateColumn, string $branchColumn, Collection $branchIds, string $statusColumn, string $status, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $periodExpression = $this->monthExpression($dateColumn);

        return $query->whereIn($branchColumn, $branchIds)->where($statusColumn, $status)
            ->whereBetween($dateColumn, [$from->toDateString(), $to->toDateString()])
            ->selectRaw($periodExpression.' as period')->selectRaw('SUM(amount) as total')
            ->groupByRaw($periodExpression)->pluck('total', 'period');
    }

    private function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /** @return list<string> */
    private function monthPeriods(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $periods = [];
        $month = $from->startOfMonth();
        $lastMonth = $to->startOfMonth();

        while ($month->lessThanOrEqualTo($lastMonth)) {
            $periods[] = $month->format('Y-m');
            $month = $month->addMonth();
        }

        return $periods;
    }

    private function hasAccess(User $user, PermissionCode $permission): bool
    {
        return $this->branchAccess->allows($user, $permission)
            || $this->branchAccess->accessibleBranchIds($user, $permission)->isNotEmpty();
    }

    /** @return Collection<int, string> */
    private function branchIds(User $user, PermissionCode $permission): Collection
    {
        return $this->branchAccess->accessibleBranchIds($user, $permission);
    }

    private function authorizeDomain(User $user, PermissionCode $permission, string $domain): void
    {
        if (! $this->hasAccess($user, $permission)) {
            throw new AuthorizationException('You do not have access to '.$domain.' analytics.');
        }
    }

    /** @param list<int|float|string> $data */
    private function dataset(string $label, array $data, string $color): array
    {
        return ['label' => $label, 'data' => array_values($data), 'color' => $color];
    }

    /**
     * @param  list<string>  $labels
     * @param  list<array{label:string, data:list<int|float>, color:string}>  $datasets
     * @param  list<array{key:string, label:string, numeric?:bool}>  $columns
     * @param  list<array<string, int|float|string>>  $rows
     * @return array<string, mixed>
     */
    private function result(string $title, string $answer, CarbonImmutable $from, CarbonImmutable $to, string $chartType, array $labels, array $datasets, array $columns, array $rows, ?string $period = null): array
    {
        return [
            'title' => $title,
            'answer' => $answer,
            'period' => $period ?? $from->format('d M Y').' – '.$to->format('d M Y'),
            'chart' => ['type' => $chartType, 'labels' => $labels, 'datasets' => $datasets],
            'columns' => $columns,
            'rows' => $rows,
        ];
    }
}
