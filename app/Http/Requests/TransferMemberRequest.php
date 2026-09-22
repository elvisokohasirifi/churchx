<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\Member;
use App\Models\User;
use App\PermissionCode;
use App\Services\BranchAccessService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'transfer_date' => ['required', 'date'],
        ];
    }
}
