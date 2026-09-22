<?php

namespace App\Http\Requests;

use App\BranchStatus;
use App\Models\Branch;
use App\Models\BranchLeader;
use App\Models\Member;
use App\PermissionCode;
use App\Services\BranchAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BranchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $branch = $this->route('id') ? Branch::query()->find($this->route('id')) : null;

        return Gate::forUser(backpack_user())->allows($branch ? 'update' : 'create', $branch ?? Branch::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $branchId = $this->route('id');
        $leaderIdRules = ['nullable', 'uuid'];
        $leaderIdRules[] = $branchId
            ? Rule::exists('branch_leaders', 'id')->where('branch_id', $branchId)
            : 'prohibited';

        return [
            'zone_id' => ['nullable', 'uuid', Rule::exists('zones', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('branches')->ignore($this->route('id'))],
            'address' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'gps_coordinates' => ['nullable', 'string', 'max:100'],
            'date_started' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(BranchStatus::class)],
            'leaders' => ['nullable', 'array', 'max:50'],
            'leaders.*.id' => $leaderIdRules,
            'leaders.*.member_id' => ['required', 'uuid', 'exists:members,id'],
            'leaders.*.leadership_title_id' => ['required', 'uuid', 'exists:leadership_titles,id'],
            'leaders.*.start_date' => ['required', 'date'],
            'leaders.*.end_date' => ['nullable', 'date', 'after_or_equal:leaders.*.start_date'],
            'leaders.*.is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $combinations = [];
            $branchId = $this->route('id');
            $hasGlobalAccess = app(BranchAccessService::class)->allows(backpack_user(), PermissionCode::BranchesManage);

            foreach ((array) $this->input('leaders', []) as $index => $leader) {
                $memberId = data_get($leader, 'member_id');
                $titleId = data_get($leader, 'leadership_title_id');

                if (! is_string($memberId) || ! is_string($titleId)) {
                    continue;
                }

                if ($branchId && ! $hasGlobalAccess && ! Member::query()->whereKey($memberId)->whereHas(
                    'primaryBranchMembership',
                    fn (Builder $query): Builder => $query->where('branch_id', $branchId),
                )->exists()) {
                    $validator->errors()->add("leaders.{$index}.member_id", 'The selected leader must belong to this branch.');
                }

                $combination = $memberId.'|'.$titleId;
                if (isset($combinations[$combination])) {
                    $validator->errors()->add("leaders.{$index}.member_id", 'Each member and leadership title combination may only be added once.');
                }

                $combinations[$combination] = true;

                $leaderId = data_get($leader, 'id');
                if (! is_string($leaderId) || ! BranchLeader::query()->whereKey($leaderId)->whereHas('assignedMembers')->exists()) {
                    continue;
                }

                $isActive = filter_var(data_get($leader, 'is_active'), FILTER_VALIDATE_BOOL);
                $startDate = data_get($leader, 'start_date');
                $endDate = data_get($leader, 'end_date');
                if (! $isActive || (is_string($startDate) && $startDate > today()->toDateString()) || (is_string($endDate) && $endDate < today()->toDateString())) {
                    $validator->errors()->add("leaders.{$index}.is_active", 'Reassign this leader’s members before ending or deactivating the appointment.');
                }
            }

            if ($branchId) {
                $submittedLeaderIds = collect((array) $this->input('leaders', []))->pluck('id')->filter();
                $removesAssignedLeader = BranchLeader::query()
                    ->where('branch_id', $branchId)
                    ->whereNotIn('id', $submittedLeaderIds)
                    ->whereHas('assignedMembers')
                    ->exists();

                if ($removesAssignedLeader) {
                    $validator->errors()->add('leaders', 'Reassign members before removing a branch leader.');
                }
            }
        }];
    }

    /**
     * Get the validation attributes that apply to the request.
     */
    public function attributes(): array
    {
        return [
            'leaders.*.member_id' => 'leader',
            'leaders.*.leadership_title_id' => 'leadership title',
            'leaders.*.start_date' => 'leader start date',
            'leaders.*.end_date' => 'leader end date',
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     */
    public function messages(): array
    {
        return [
            //
        ];
    }
}
