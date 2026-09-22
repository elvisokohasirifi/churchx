<?php

namespace App\Http\Requests;

use App\PermissionCode;
use Illuminate\Foundation\Http\FormRequest;

class HouseholdRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // only allow updates if the user is logged in
        return backpack_user()?->can(PermissionCode::MembersUpdate->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'family_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
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
