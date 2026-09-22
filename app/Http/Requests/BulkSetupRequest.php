<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\SetupBulkRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkSetupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = backpack_user();

        return $user instanceof User
            && ($user->hasActiveRole('App Administrator') || $user->hasActiveRole('Church Administrator'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return app(SetupBulkRegistry::class)->rules((string) $this->route('resource'));
    }

    public function messages(): array
    {
        return [
            'items.*.name.distinct' => 'Names must be unique within this bulk submission.',
            'items.*.name.unique' => 'One or more names already exist.',
        ];
    }
}
