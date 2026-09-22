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
use Illuminate\Validation\Validator;

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
            'branch_leader_id' => ['required', 'uuid', 'exists:branch_leaders,id'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'date_joined' => ['required', 'date'],
            'membership_status' => ['required', Rule::enum(MemberStatus::class)],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('branch_leader_id')) {
                return;
            }

            $visitor = $this->route('visitor');
            $isValidLeader = $visitor instanceof Visitor && $visitor->branch
                ->leaders()
                ->whereKey($this->input('branch_leader_id'))
                ->where('is_active', true)
                ->whereDate('start_date', '<=', today())
                ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
                ->exists();

            if (! $isValidLeader) {
                $validator->errors()->add('branch_leader_id', 'Select an active leader from the visitor’s branch.');
            }
        }];
    }
}
