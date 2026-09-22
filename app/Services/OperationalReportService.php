<?php

namespace App\Services;

use App\IncomeStatus;
use App\Models\AttendanceSummary;
use App\Models\Branch;
use App\Models\BranchLeader;
use App\Models\Income;
use App\Models\Member;
use App\Models\MemberAttendance;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class OperationalReportService
{
    /** @param Collection<int, string> $branchIds */
    public function attendanceRecords(Collection $branchIds, CarbonImmutable $from, CarbonImmutable $to, ?string $branchId = null, ?string $search = null): Collection
    {
        $scopeIds = $this->scopeIds($branchIds, $branchId);
        $attendance = MemberAttendance::query()
            ->join('services', 'services.id', '=', 'member_attendances.service_id')
            ->whereBetween('services.date', [$from->toDateString(), $to->toDateString()])
            ->select('member_attendances.member_id')
            ->selectRaw("sum(case when member_attendances.status = 'present' then 1 else 0 end) as present_count")
            ->selectRaw("sum(case when member_attendances.status = 'absent' then 1 else 0 end) as absent_count")
            ->selectRaw("max(case when member_attendances.status = 'present' then services.date end) as last_present_date")
            ->groupBy('member_attendances.member_id')
            ->get()
            ->keyBy('member_id');

        return Member::query()
            ->with(['primaryBranchMembership.branch', 'branchLeader.member', 'shepherd'])
            ->whereHas('primaryBranchMembership', fn ($query) => $query->whereIn('branch_id', $scopeIds))
            ->when($search, fn ($query, $term) => $query->where(function ($query) use ($term): void {
                $query->where('first_name', 'like', "%{$term}%")
                    ->orWhere('middle_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('membership_number', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            }))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(function (Member $member) use ($attendance): array {
                $record = $attendance->get($member->id);
                $present = (int) ($record?->present_count ?? 0);
                $absent = (int) ($record?->absent_count ?? 0);
                $marked = $present + $absent;

                return [
                    'member' => $member,
                    'branch' => $member->primaryBranchMembership?->branch,
                    'present' => $present,
                    'absent' => $absent,
                    'marked' => $marked,
                    'rate' => $marked > 0 ? round(($present / $marked) * 100, 1) : null,
                    'last_present_date' => $record?->last_present_date,
                ];
            });
    }

    /** @param Collection<int, string> $branchIds */
    public function attendanceByChurch(Collection $branchIds, CarbonImmutable $from, CarbonImmutable $to, ?string $branchId = null): Collection
    {
        $scopeIds = $this->scopeIds($branchIds, $branchId);
        $metrics = $this->attendanceMetrics($scopeIds, $from, $to);

        return Branch::query()
            ->whereIn('id', $scopeIds)
            ->orderBy('name')
            ->get()
            ->map(function (Branch $branch) use ($metrics): array {
                $metric = $metrics->get($branch->id);
                $services = (int) ($metric?->services_count ?? 0);
                $total = (int) ($metric?->attendance_total ?? 0);

                return [
                    'branch' => $branch,
                    'services' => $services,
                    'attendance' => $total,
                    'average' => $services > 0 ? round($total / $services, 1) : 0.0,
                ];
            })
            ->sortByDesc('attendance')
            ->values();
    }

    /** @param Collection<int, string> $branchIds */
    public function attendanceByLeader(Collection $branchIds, CarbonImmutable $from, CarbonImmutable $to, ?string $branchId = null): Collection
    {
        return $this->leaderRankings(
            $this->scopeIds($branchIds, $branchId),
            $from,
            $to,
            fn (Collection $scopeIds): Collection => $this->attendanceMetrics($scopeIds, $from, $to),
            'attendance_total',
        );
    }

    /** @param Collection<int, string> $branchIds */
    public function givingByService(Collection $branchIds, CarbonImmutable $from, CarbonImmutable $to, ?string $branchId = null): Collection
    {
        $scopeIds = $this->scopeIds($branchIds, $branchId);
        $totals = Income::query()
            ->whereIn('branch_id', $scopeIds)
            ->whereNotNull('service_id')
            ->where('status', IncomeStatus::Completed->value)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->select('service_id')
            ->selectRaw('count(*) as entries_count, sum(amount) as giving_total')
            ->groupBy('service_id')
            ->get()
            ->keyBy('service_id');

        $serviceBranches = Income::query()
            ->whereIn('branch_id', $scopeIds)
            ->whereNotNull('service_id')
            ->where('status', IncomeStatus::Completed->value)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->with('branch:id,name')
            ->get(['service_id', 'branch_id'])
            ->groupBy('service_id')
            ->map(fn (Collection $rows): string => $rows->pluck('branch.name')->filter()->unique()->sort()->join(', '));

        return Service::query()
            ->whereIn('id', $totals->keys())
            ->orderByDesc('date')
            ->orderBy('name')
            ->get()
            ->map(fn (Service $service): array => [
                'service' => $service,
                'branches' => $serviceBranches->get($service->id, ''),
                'entries' => (int) $totals->get($service->id)->entries_count,
                'giving' => (float) $totals->get($service->id)->giving_total,
            ]);
    }

    /** @param Collection<int, string> $branchIds */
    public function givingByLeader(Collection $branchIds, CarbonImmutable $from, CarbonImmutable $to, ?string $branchId = null): Collection
    {
        return $this->leaderRankings(
            $this->scopeIds($branchIds, $branchId),
            $from,
            $to,
            fn (Collection $scopeIds): Collection => Income::query()
                ->whereIn('branch_id', $scopeIds)
                ->where('status', IncomeStatus::Completed->value)
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->select('branch_id')
                ->selectRaw('count(distinct service_id) as services_count, sum(amount) as giving_total')
                ->groupBy('branch_id')
                ->get()
                ->keyBy('branch_id'),
            'giving_total',
        );
    }

    /** @param Collection<int, string> $branchIds */
    public function absenceFollowUp(Collection $branchIds, CarbonImmutable $from, CarbonImmutable $to, ?string $branchId = null, int $minimumAbsences = 2, ?string $search = null): Collection
    {
        return $this->attendanceRecords($branchIds, $from, $to, $branchId, $search)
            ->filter(fn (array $row): bool => $row['absent'] >= $minimumAbsences)
            ->sortByDesc('absent')
            ->values();
    }

    /** @param Collection<int, string> $scopeIds */
    private function attendanceMetrics(Collection $scopeIds, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return AttendanceSummary::query()
            ->join('services', 'services.id', '=', 'attendance_summaries.service_id')
            ->whereIn('attendance_summaries.branch_id', $scopeIds)
            ->whereBetween('services.date', [$from->toDateString(), $to->toDateString()])
            ->select('attendance_summaries.branch_id')
            ->selectRaw('count(distinct attendance_summaries.service_id) as services_count')
            ->selectRaw('sum(total_male + total_female + total_children) as attendance_total')
            ->groupBy('attendance_summaries.branch_id')
            ->get()
            ->keyBy('branch_id');
    }

    /**
     * @param  Collection<int, string>  $scopeIds
     * @param  callable(Collection<int, string>): Collection  $metricsResolver
     */
    private function leaderRankings(Collection $scopeIds, CarbonImmutable $from, CarbonImmutable $to, callable $metricsResolver, string $totalKey): Collection
    {
        $metrics = $metricsResolver($scopeIds);
        $leaders = BranchLeader::query()
            ->with(['member', 'branch'])
            ->whereIn('branch_id', $scopeIds)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $to)
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $from))
            ->get()
            ->groupBy('member_id');

        return $leaders->map(function (Collection $appointments) use ($metrics, $totalKey): array {
            $uniqueAppointments = $appointments->unique('branch_id');
            $branchMetrics = $uniqueAppointments->map(fn (BranchLeader $leader) => $metrics->get($leader->branch_id))->filter();
            $services = (int) $branchMetrics->sum(fn ($metric) => (int) ($metric->services_count ?? 0));
            $total = (float) $branchMetrics->sum(fn ($metric) => (float) ($metric->{$totalKey} ?? 0));

            return [
                'leader' => $appointments->first()->member,
                'branches' => $uniqueAppointments->pluck('branch.name')->filter()->unique()->sort()->join(', '),
                'services' => $services,
                'total' => $total,
                'average' => $services > 0 ? round($total / $services, 1) : 0.0,
            ];
        })->sortByDesc('total')->values();
    }

    /** @param Collection<int, string> $branchIds */
    private function scopeIds(Collection $branchIds, ?string $branchId): Collection
    {
        return $branchId === null ? $branchIds : collect([$branchId]);
    }
}
