<?php

namespace App\Http\Requests;

use App\MemberStatus;
use App\Models\User;
use App\Models\Visitor;
use App\PermissionCode;
use App\Services\BranchAccessService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertVisitorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = backpack_user();
        $visitor = $this->route('visitor');

        return $user instanceof User
            && $visitor instanceof Visitor
            && $user->can('update', $visitor)
            && app(BranchAccessService::class)->allows($user, PermissionCode::MembersCreate, $visitor->branch_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'date_joined' => ['required', 'date'],
            'membership_status' => ['required', Rule::enum(MemberStatus::class)],
        ];
    }
}
