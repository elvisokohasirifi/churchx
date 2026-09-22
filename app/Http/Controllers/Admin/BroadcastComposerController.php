<?php

namespace App\Http\Controllers\Admin;

use App\BroadcastStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBroadcastRequest;
use App\Jobs\DeliverBroadcast;
use App\Models\Branch;
use App\Models\BranchDepartment;
use App\Models\Broadcast;
use App\Models\ChurchGroup;
use App\PermissionCode;
use App\Services\MemberAudienceFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BroadcastComposerController extends Controller
{
    public function create(MemberAudienceFilter $memberAudienceFilter): View
    {
        abort_unless(backpack_user()->can(PermissionCode::BroadcastsCreate->value), 403);

        return view('admin.broadcasts.compose', [
            'branches' => Branch::query()->orderBy('name')->get(),
            'branchDepartments' => BranchDepartment::query()
                ->with(['branch', 'department'])
                ->where('is_active', true)
                ->get()
                ->sortBy(fn (BranchDepartment $branchDepartment): string => $branchDepartment->branch->name.' '.$branchDepartment->department->name),
            'groups' => ChurchGroup::query()->with('branch')->whereNotNull('branch_id')->orderBy('name')->get(),
            'memberFields' => $memberAudienceFilter::FIELDS,
            'memberFilterOperators' => $memberAudienceFilter::OPERATORS,
            'broadcasts' => Broadcast::query()->latest()->paginate(20),
        ]);
    }

    public function store(StoreBroadcastRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $broadcast = Broadcast::query()->create([
            'title' => $data['title'],
            'message' => $data['message'],
            'channel' => $data['channel'],
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'created_by' => backpack_user()->id,
            'status' => filled($data['scheduled_at'] ?? null) ? BroadcastStatus::Scheduled : BroadcastStatus::Draft,
        ]);
        $broadcast->audiences()->create([
            'audience_type' => $data['audience_type'],
            'audience_id' => match ($data['audience_type']) {
                'branch' => $data['branch_id'],
                'department' => $data['department_id'],
                'group' => $data['group_id'],
                default => null,
            },
            'branch_id' => $data['audience_type'] === 'all_members' ? null : $data['branch_id'],
            'filter_field' => $data['audience_type'] === 'all_members' ? ($data['filter_field'] ?? null) : null,
            'filter_operator' => $data['audience_type'] === 'all_members' ? ($data['filter_operator'] ?? null) : null,
            'filter_value' => $data['audience_type'] === 'all_members'
                && MemberAudienceFilter::requiresCondition($data['filter_operator'] ?? null)
                    ? ($data['filter_value'] ?? null)
                    : null,
        ]);

        return back()->with('success', 'Broadcast saved.');
    }

    public function send(Broadcast $broadcast): RedirectResponse
    {
        abort_unless(backpack_user()->can(PermissionCode::BroadcastsSend->value), 403);
        abort_unless(in_array($broadcast->status, [BroadcastStatus::Draft, BroadcastStatus::Scheduled], true), 422);
        $broadcast->update(['status' => BroadcastStatus::Processing]);
        DeliverBroadcast::dispatch($broadcast);

        return back()->with('success', 'Broadcast queued for delivery.');
    }
}
