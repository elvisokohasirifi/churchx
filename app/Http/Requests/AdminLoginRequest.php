<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminLoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login_method' => ['required', Rule::in(['email', 'phone'])],
            'email' => ['nullable', 'required_if:login_method,email', 'email', 'max:255'],
            'password' => ['nullable', 'required_if:login_method,email', 'string'],
            'phone' => ['nullable', 'required_if:login_method,phone', 'string', 'max:30'],
            'pin' => ['nullable', 'required_if:login_method,phone', 'string', 'regex:/^\d{4,8}$/'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }
}
