<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\MemberBranchStatus;
use App\Models\AttendanceSummary;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberAttendance;
use App\Models\Service;
use App\PermissionCode;
use App\Services\BranchAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceRegisterController extends Controller
{
    public function index(Request $request, BranchAccessService $access): View
    {
        $branchIds = $access->accessibleBranchIds(backpack_user(), PermissionCode::AttendanceCapture);
        abort_if($branchIds->isEmpty(), 403);
        $branchId = $request->string('branch_id')->toString() ?: $branchIds->first();
        abort_unless($branchIds->contains($branchId), 403);

        $services = $this->servicesForBranch($branchId)->get();
        $serviceId = $request->string('service_id')->toString() ?: $services->first()?->id;
        $members = $serviceId
            ? $this->membersForBranch($branchId)->with(['attendances' => fn ($query) => $query->where('service_id', $serviceId)])->get()
            : collect();

        return view('admin.attendance.register', [
            'branches' => Branch::query()->whereIn('id', $branchIds)->orderBy('name')->get(),
            'services' => $services,
            'members' => $members,
            'branchId' => $branchId,
            'serviceId' => $serviceId,
        ]);
    }

    public function store(Request $request, BranchAccessService $access): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'uuid', 'exists:branches,id'],
            'service_id' => ['required', 'uuid', 'exists:services,id'],
            'attendance' => ['required', 'array', 'min:1'],
            'attendance.*' => ['required', Rule::in(['present', 'absent'])],
        ]);
        abort_unless($access->allows(backpack_user(), PermissionCode::AttendanceCapture, $data['branch_id']), 403);
        abort_unless($this->servicesForBranch($data['branch_id'])->whereKey($data['service_id'])->exists(), 422, 'The service is not available to this branch.');

        $memberIds = $this->membersForBranch($data['branch_id'])->pluck('id');
        abort_unless($memberIds->sort()->values()->all() === collect(array_keys($data['attendance']))->sort()->values()->all(), 422, 'Attendance must be marked for every active branch member.');

        DB::transaction(function () use ($data, $memberIds): void {
            foreach ($data['attendance'] as $memberId => $status) {
                MemberAttendance::query()->updateOrCreate(
                    ['service_id' => $data['service_id'], 'member_id' => $memberId],
                    ['status' => $status, 'checked_in_at' => $status === 'present' ? now() : null],
                );
            }

            $presentMembers = Member::query()->whereIn('id', $memberIds)
                ->whereIn('id', collect($data['attendance'])->filter(fn (string $status): bool => $status === 'present')->keys())
                ->get();
            $existing = AttendanceSummary::query()->where(['service_id' => $data['service_id'], 'branch_id' => $data['branch_id']])->first();
            AttendanceSummary::query()->updateOrCreate(
                ['service_id' => $data['service_id'], 'branch_id' => $data['branch_id']],
                [
                    'total_male' => $presentMembers->where('gender', 'male')->count(),
                    'total_female' => $presentMembers->where('gender', 'female')->count(),
                    'total_children' => 0,
                    'total_members' => $presentMembers->count(),
                    'total_visitors' => $existing?->total_visitors ?? 0,
                    'captured_by' => backpack_user()->id,
                ],
            );
        });

        return redirect()->route('admin.attendance.register', ['branch_id' => $data['branch_id'], 'service_id' => $data['service_id']])->with('success', 'Attendance register saved.');
    }

    private function servicesForBranch(string $branchId): Builder
    {
        return Service::query()->where(function (Builder $query) use ($branchId): void {
            $query->where('branch_id', $branchId)
                ->orWhereHas('branches', fn (Builder $branches) => $branches->whereKey($branchId))
                ->orWhere('scope', 'church_wide');
        })->latest('date');
    }

    private function membersForBranch(string $branchId): Builder
    {
        return Member::query()->whereHas('primaryBranchMembership', fn (Builder $query) => $query
            ->where('branch_id', $branchId)
            ->where('status', MemberBranchStatus::Active->value)
            ->whereNull('left_date'))
            ->orderBy('first_name')
            ->orderBy('last_name');
    }
}
