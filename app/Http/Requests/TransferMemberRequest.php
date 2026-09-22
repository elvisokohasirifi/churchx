<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\Member;
use App\Models\User;
use App\PermissionCode;
use App\Services\BranchAccessService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TransferMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = backpack_user();
        $member = $this->route('member');
        $branch = Branch::query()->find($this->input('branch_id'));

        return $user instanceof User
            && $member instanceof Member
            && $branch !== null
            && $user->can('update', $member)
            && app(BranchAccessService::class)->allows($user, PermissionCode::MembersUpdate, $branch);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'uuid', 'exists:branches,id'],
            'branch_leader_id' => ['required', 'uuid', 'exists:branch_leaders,id'],
            'transfer_date' => ['required', 'date'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['branch_id', 'branch_leader_id', 'transfer_date'])) {
                return;
            }

            $transferDate = $this->date('transfer_date');
            $isValidLeader = Branch::query()
                ->find($this->input('branch_id'))
                ?->leaders()
                ->whereKey($this->input('branch_leader_id'))
                ->where('is_active', true)
                ->whereDate('start_date', '<=', $transferDate)
                ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $transferDate))
                ->exists() ?? false;

            if (! $isValidLeader) {
                $validator->errors()->add('branch_leader_id', 'Select an active leader from the destination branch.');
            }
        }];
    }
}
