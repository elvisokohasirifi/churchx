<?php

namespace App\Http\Requests;

use App\MemberStatus;
use App\Models\Branch;
use App\Models\BranchLeader;
use App\Models\Member;
use App\Models\User;
use App\PermissionCode;
use App\Services\BranchAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MemberRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (filled($this->input('branch_leader_id'))) {
            return;
        }

        $member = $this->route('id') ? Member::query()->find($this->route('id')) : null;
        $branchId = $this->input('primary_branch_id')
            ?: $member?->primaryBranchMembership()->value('branch_id');
        $branchLeaderId = BranchLeader::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', today())
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
            ->orderBy('start_date')
            ->orderBy('id')
            ->value('id');

        if ($branchLeaderId !== null) {
            $this->merge(['branch_leader_id' => $branchLeaderId]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = backpack_auth()->user();
        $member = $this->route('id') ? Member::query()->find($this->route('id')) : null;

        if (! $user instanceof User) {
            return false;
        }

        if ($member !== null) {
            return $user->can('update', $member);
        }

        $branch = Branch::query()->find($this->input('primary_branch_id'));

        return $branch !== null && app(BranchAccessService::class)->allows($user, PermissionCode::MembersCreate, $branch);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'primary_branch_id' => [$this->route('id') ? 'nullable' : 'required', 'uuid', 'exists:branches,id'],
            'shepherd_id' => ['nullable', 'uuid', 'exists:members,id'],
            'branch_leader_id' => ['nullable', 'uuid', 'exists:branch_leaders,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'alternative_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'max:30'],
            'marital_status' => ['nullable', 'string', 'max:50'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'highest_education' => ['nullable', 'string', 'max:255'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'date_joined' => ['nullable', 'date'],
            'membership_status' => ['required', Rule::enum(MemberStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['primary_branch_id', 'branch_leader_id'])) {
                return;
            }

            $member = $this->route('id') ? Member::query()->find($this->route('id')) : null;
            $branchId = $this->input('primary_branch_id')
                ?: $member?->primaryBranchMembership()->value('branch_id');
            $activeBranchLeaders = BranchLeader::query()
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->whereDate('start_date', '<=', today())
                ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()));
            $branchLeader = filled($this->input('branch_leader_id'))
                ? (clone $activeBranchLeaders)->whereKey($this->input('branch_leader_id'))->first()
                : null;

            if (blank($this->input('branch_leader_id')) && (clone $activeBranchLeaders)->exists()) {
                $validator->errors()->add('branch_leader_id', 'Select an active branch leader from the member’s primary branch.');
            } elseif (filled($this->input('branch_leader_id')) && $branchLeader === null) {
                $validator->errors()->add('branch_leader_id', 'Select an active branch leader from the member’s primary branch.');
            }

            $shepherdId = $this->input('shepherd_id');

            if (blank($shepherdId)) {
                return;
            }

            if ($branchLeader?->member_id === $shepherdId) {
                $validator->errors()->add('branch_leader_id', 'The branch leader must be different from the shepherd.');

                return;
            }

            if ($member?->getKey() === $shepherdId) {
                $validator->errors()->add('shepherd_id', 'A member cannot be their own shepherd.');

                return;
            }

            if ($branchId !== null && ! Member::query()->whereKey($shepherdId)->whereHas(
                'primaryBranchMembership',
                fn ($query) => $query->where('branch_id', $branchId),
            )->exists()) {
                $validator->errors()->add('shepherd_id', 'The shepherd must belong to the same primary branch as the member.');
            }
        }];
    }

    /**
     * Get the validation attributes that apply to the request.
     */
    public function attributes(): array
    {
        return [
            'branch_leader_id' => 'branch leader',
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
