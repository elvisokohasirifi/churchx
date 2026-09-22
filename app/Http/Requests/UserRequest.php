<?php

namespace App\Http\Requests;

use App\PermissionCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::forUser(backpack_user())->allows(PermissionCode::UsersManage->value);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users')->ignore($userId)],
            'password' => [$userId ? 'nullable' : 'required', Password::defaults()],
            'pin' => ['nullable', 'regex:/^\d{4,8}$/'],
            'is_active' => ['required', 'boolean'],
            'role_id' => [Rule::requiredIf($this->isMethod('post')), 'nullable', 'uuid', 'exists:roles,id'],
            'branch_ids' => ['nullable', 'array', 'max:100'],
            'branch_ids.*' => ['uuid', 'distinct', 'exists:branches,id'],
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
