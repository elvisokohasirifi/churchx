<?php

namespace App\Http\Requests;

use App\Models\UserRole;
use App\PermissionCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UserRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::forUser(backpack_user())->allows(PermissionCode::RolesManage->value);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $assignmentId = $this->route('id');

        return [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'role_id' => ['required', 'uuid', 'exists:roles,id'],
            'branch_id' => ['prohibited'],
            'branch_ids' => ['nullable', 'array', 'max:100'],
            'branch_ids.*' => ['uuid', 'distinct', 'exists:branches,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $assignmentId = $this->route('id');
            $assignment = $assignmentId ? UserRole::query()->find($assignmentId) : null;
            $excludedAssignmentIds = collect($assignmentId ? [$assignmentId] : []);

            if ($assignment
                && $assignment->user_id === $this->input('user_id')
                && $assignment->role_id === $this->input('role_id')) {
                $excludedAssignmentIds = UserRole::query()
                    ->where('user_id', $assignment->user_id)
                    ->where('role_id', $assignment->role_id)
                    ->pluck('id');
            }

            $branchIds = $this->input('branch_ids', []);
            $scopes = is_array($branchIds) && $branchIds !== [] ? $branchIds : [null];

            foreach ($scopes as $branchId) {
                $exists = UserRole::query()
                    ->where('user_id', $this->input('user_id'))
                    ->where('role_id', $this->input('role_id'))
                    ->whereNotIn('id', $excludedAssignmentIds)
                    ->when($branchId === null, fn ($query) => $query->whereNull('branch_id'), fn ($query) => $query->where('branch_id', $branchId))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('branch_ids', 'This user already has the selected role for one or more selected scopes.');

                    return;
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
