<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransferMemberRequest;
use App\Models\Branch;
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

        return view('admin.members.transfer', compact('member', 'branches'));
    }

    public function store(TransferMemberRequest $request, Member $member, MemberTransferService $transfers): RedirectResponse
    {
        $branch = Branch::query()->findOrFail($request->validated('branch_id'));
        $transfers->transfer($member, $branch, $request->date('transfer_date'), backpack_user());

        return redirect()->route('members.show', $member)->with('success', 'Member transferred successfully.');
    }
}
