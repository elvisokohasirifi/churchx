<?php

namespace App\Http\Requests;

use App\Models\Church;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChurchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = backpack_auth()->user();
        $church = Church::query()->first();

        return $user instanceof User && $church !== null && $user->can('update', $church);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'country' => ['nullable', 'string', 'max:100'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'timezone:all'],
        ];
    }
}
