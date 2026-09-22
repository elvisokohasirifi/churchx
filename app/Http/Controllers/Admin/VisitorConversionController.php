<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConvertVisitorRequest;
use App\MemberStatus;
use App\Models\BranchLeader;
use App\Models\Visitor;
use App\Services\VisitorConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VisitorConversionController extends Controller
{
    public function create(Visitor $visitor): View
    {
        Gate::forUser(backpack_user())->authorize('update', $visitor);
        abort_if($visitor->converted_to_member_id !== null, 409, 'This visitor has already been converted.');
        $names = preg_split('/\s+/', trim($visitor->name)) ?: [];
        $branchLeaders = BranchLeader::query()
            ->with(['member:id,first_name,middle_name,last_name', 'leadershipTitle:id,name'])
            ->where('branch_id', $visitor->branch_id)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', today())
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
            ->get();

        return view('admin.visitors.convert', [
            'visitor' => $visitor,
            'firstName' => array_shift($names) ?: $visitor->name,
            'lastName' => array_pop($names) ?: '-',
            'middleName' => $names ? implode(' ', $names) : null,
            'statuses' => MemberStatus::cases(),
            'branchLeaders' => $branchLeaders,
        ]);
    }

    public function store(ConvertVisitorRequest $request, Visitor $visitor, VisitorConversionService $conversion): RedirectResponse
    {
        $member = $conversion->convert($visitor, $request->validated(), actor: backpack_user());

        return redirect()->route('members.show', $member)->with('success', 'Visitor converted to a member.');
    }
}
