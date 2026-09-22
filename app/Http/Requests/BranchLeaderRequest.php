<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\PermissionCode;
use App\Services\BranchAccessService;
use Illuminate\Foundation\Http\FormRequest;

class BranchLeaderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // only allow updates if the user is logged in
        $branch = Branch::query()->find($this->input('branch_id'));

        return $branch !== null && app(BranchAccessService::class)->allows(backpack_user(), PermissionCode::BranchesManage, $branch);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'uuid', 'exists:branches,id'],
            'member_id' => ['required', 'uuid', 'exists:members,id'],
            'leadership_title_id' => ['required', 'uuid', 'exists:leadership_titles,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Get the validation attributes that apply to the request.
     */
    public function attributes(): array
    {
        return [
            //
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
