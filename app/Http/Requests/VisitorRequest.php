<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\User;
use App\Models\Visitor;
use App\PermissionCode;
use App\Services\BranchAccessService;
use Illuminate\Foundation\Http\FormRequest;

class VisitorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = backpack_auth()->user();
        $visitor = $this->route('id') ? Visitor::query()->find($this->route('id')) : null;

        if (! $user instanceof User) {
            return false;
        }
        if ($visitor !== null) {
            return $user->can('update', $visitor);
        }

        $branch = Branch::query()->find($this->input('branch_id'));

        return $branch !== null && app(BranchAccessService::class)->allows($user, PermissionCode::VisitorsCreate, $branch);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'gender' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'invited_by_member_id' => ['nullable', 'uuid', 'exists:members,id'],
            'branch_id' => ['required', 'uuid', 'exists:branches,id'],
            'first_visit_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
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
