<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class BranchCsvImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = backpack_user();

        return $user instanceof User && Gate::forUser($user)->allows('create', Branch::class);
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
            'csv_file.required' => 'Choose a branch CSV file to upload.',
            'csv_file.mimes' => 'The branch import must be a valid CSV file.',
            'csv_file.extensions' => 'The branch import file must use the .csv extension.',
        ];
    }
}
