<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class InitialRegistrationRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'church_name' => ['required', 'string', 'max:255'],
            'church_email' => ['nullable', 'email', 'max:255'],
            'church_phone' => ['nullable', 'string', 'max:30'],
            'church_website' => ['nullable', 'url:http,https', 'max:255'],
            'church_address' => ['nullable', 'string', 'max:2000'],
            'church_country' => ['required', 'string', 'max:100'],
            'church_currency' => ['required', 'alpha', 'size:3'],
            'church_timezone' => ['required', 'timezone:all'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'pin' => ['nullable', 'confirmed', 'regex:/^\d{4,8}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower($this->string('email')->trim()->toString()),
            'church_currency' => Str::upper($this->string('church_currency')->trim()->toString()),
            'phone' => $this->filled('phone') ? preg_replace('/\s+/', '', $this->string('phone')->toString()) : null,
        ]);
    }
}
