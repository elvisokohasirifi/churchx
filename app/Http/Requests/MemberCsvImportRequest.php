<?php

namespace App\Http\Requests;

use App\Models\User;
use App\PermissionCode;
use App\Services\BranchAccessService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MemberCsvImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = backpack_user();

        if (! $user instanceof User) {
            return false;
        }

        $access = app(BranchAccessService::class);

        return $access->allows($user, PermissionCode::MembersCreate)
            || $access->accessibleBranchIds($user, PermissionCode::MembersCreate)->isNotEmpty();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'extensions:csv', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'csv_file.required' => 'Choose a member CSV file to upload.',
            'csv_file.mimes' => 'The member import must be a valid CSV file.',
            'csv_file.extensions' => 'The member import file must use the .csv extension.',
        ];
    }
}
