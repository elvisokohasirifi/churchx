<?php

namespace App\Http\Requests;

use App\PermissionCode;
use Illuminate\Foundation\Http\FormRequest;

class LeadershipTitleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return backpack_user()?->can(PermissionCode::BranchesManage->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:leadership_titles,name,'.$this->route('id')],
            'description' => ['nullable', 'string', 'max:2000'],
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
