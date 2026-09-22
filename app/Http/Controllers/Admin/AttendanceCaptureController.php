<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Service;
use App\PermissionCode;
use App\Services\AttendanceCaptureService;
use App\Services\BranchAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceCaptureController extends Controller
{
    public function create(BranchAccessService $access): View
    {
        abort_unless(backpack_user()->can(PermissionCode::AttendanceCapture->value), 403);
        $branchIds = $access->accessibleBranchIds(backpack_user(), PermissionCode::AttendanceCapture);

        return view('admin.attendance.capture', [
            'branches' => Branch::query()->whereIn('id', $branchIds)->orderBy('name')->get(),
            'services' => Service::query()->where(function (Builder $query) use ($branchIds): void {
                $query->whereIn('branch_id', $branchIds)
                    ->orWhereHas('branches', fn (Builder $branches) => $branches->whereIn('branches.id', $branchIds))
                    ->orWhere('scope', 'church_wide');
            })->latest('date')->get(),
        ]);
    }

    public function store(Request $request, AttendanceCaptureService $capture, BranchAccessService $access): RedirectResponse
    {
        $data = $request->validate(['service_id' => ['required', 'uuid', 'exists:services,id'], 'branch_id' => ['required', 'uuid', 'exists:branches,id'], 'total_male' => ['required', 'integer', 'min:0'], 'total_female' => ['required', 'integer', 'min:0'], 'total_children' => ['required', 'integer', 'min:0'], 'total_members' => ['required', 'integer', 'min:0'], 'total_visitors' => ['required', 'integer', 'min:0']]);
        abort_unless($access->allows(backpack_user(), PermissionCode::AttendanceCapture, $data['branch_id']), 403);
        $service = Service::query()->whereKey($data['service_id'])->where(function (Builder $query) use ($data): void {
            $query->where('branch_id', $data['branch_id'])
                ->orWhereHas('branches', fn (Builder $branches) => $branches->whereKey($data['branch_id']))
                ->orWhere('scope', 'church_wide');
        })->firstOrFail();
        $capture->capture($service, Branch::query()->findOrFail($data['branch_id']), backpack_user(), array_diff_key($data, array_flip(['service_id', 'branch_id'])));

        return back()->with('success', 'Attendance summary saved.');
    }
}
