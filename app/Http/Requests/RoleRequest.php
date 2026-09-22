<?php

namespace App\Http\Requests;

use App\PermissionCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($this->route('id'))],
            'description' => ['nullable', 'string', 'max:2000'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['uuid', 'exists:permissions,id'],
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
