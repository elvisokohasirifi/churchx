<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransferMemberRequest;
use App\Models\Branch;
use App\Models\BranchLeader;
use App\Models\Member;
use App\PermissionCode;
use App\Services\BranchAccessService;
use App\Services\MemberTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MemberTransferController extends Controller
{
    public function create(Member $member, BranchAccessService $access): View
    {
        Gate::forUser(backpack_user())->authorize('update', $member);
        $branches = Branch::query()
            ->whereIn('id', $access->accessibleBranchIds(backpack_user(), PermissionCode::MembersUpdate))
            ->orderBy('name')
            ->get();
        $branchLeaders = BranchLeader::query()
            ->with(['member:id,first_name,middle_name,last_name', 'leadershipTitle:id,name'])
            ->whereIn('branch_id', $branches->pluck('id'))
            ->where('is_active', true)
            ->whereDate('start_date', '<=', today())
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
            ->get();

        return view('admin.members.transfer', compact('member', 'branches', 'branchLeaders'));
    }

    public function store(TransferMemberRequest $request, Member $member, MemberTransferService $transfers): RedirectResponse
    {
        $branch = Branch::query()->findOrFail($request->validated('branch_id'));
        $branchLeader = BranchLeader::query()->findOrFail($request->validated('branch_leader_id'));
        $transfers->transfer($member, $branch, $branchLeader, $request->date('transfer_date'), backpack_user());

        return redirect()->route('members.show', $member)->with('success', 'Member transferred successfully.');
    }
}
