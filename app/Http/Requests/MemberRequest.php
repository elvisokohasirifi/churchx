<?php

namespace App\Http\Requests;

use App\MemberStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\User;
use App\PermissionCode;
use App\Services\BranchAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = backpack_auth()->user();
        $member = $this->route('id') ? Member::query()->find($this->route('id')) : null;

        if (! $user instanceof User) {
            return false;
        }

        if ($member !== null) {
            return $user->can('update', $member);
        }

        $branch = Branch::query()->find($this->input('primary_branch_id'));

        return $branch !== null && app(BranchAccessService::class)->allows($user, PermissionCode::MembersCreate, $branch);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'primary_branch_id' => [$this->route('id') ? 'nullable' : 'required', 'uuid', 'exists:branches,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'alternative_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'max:30'],
            'marital_status' => ['nullable', 'string', 'max:50'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'highest_education' => ['nullable', 'string', 'max:255'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'date_joined' => ['nullable', 'date'],
            'membership_status' => ['required', Rule::enum(MemberStatus::class)],
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
