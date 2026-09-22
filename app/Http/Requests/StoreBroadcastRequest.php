<?php

namespace App\Http\Requests;

use App\Models\User;
use App\PermissionCode;
use App\Services\MemberAudienceFilter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBroadcastRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = backpack_auth()->user();

        return $user instanceof User && $user->can(PermissionCode::BroadcastsCreate->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
            'channel' => ['required', Rule::in(['sms', 'email', 'push', 'whatsapp'])],
            'scheduled_at' => ['nullable', 'date'],
            'audience_type' => ['required', Rule::in(['all_members', 'branch', 'department', 'group'])],
            'branch_id' => ['nullable', 'required_if:audience_type,branch,department,group', 'uuid', Rule::exists('branches', 'id')],
            'department_id' => [
                'nullable',
                'required_if:audience_type,department',
                'uuid',
                Rule::exists('branch_departments', 'department_id')
                    ->where('branch_id', $this->input('branch_id'))
                    ->where('is_active', true),
            ],
            'group_id' => [
                'nullable',
                'required_if:audience_type,group',
                'uuid',
                Rule::exists('church_groups', 'id')->where('branch_id', $this->input('branch_id')),
            ],
            'filter_field' => ['nullable', 'string', Rule::in(array_keys(MemberAudienceFilter::FIELDS))],
            'filter_operator' => ['nullable', 'string', Rule::in(array_keys(MemberAudienceFilter::OPERATORS))],
            'filter_value' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['audience_type', 'filter_field', 'filter_operator', 'filter_value'])) {
                    return;
                }

                $field = $this->input('filter_field');
                $operator = $this->input('filter_operator');
                $condition = $this->input('filter_value');
                $hasFilterInput = filled($field) || filled($operator) || filled($condition);

                if ($this->input('audience_type') !== 'all_members') {
                    if ($hasFilterInput) {
                        $validator->errors()->add('filter_field', 'Member filters can only be used with the All members audience.');
                    }

                    return;
                }

                if (! $hasFilterInput) {
                    return;
                }

                if (blank($field)) {
                    $validator->errors()->add('filter_field', 'Please choose a member field.');
                }
                if (blank($operator)) {
                    $validator->errors()->add('filter_operator', 'Please choose a filter operator.');
                }
                if (filled($operator) && ! MemberAudienceFilter::conditionIsValid($operator, $condition)) {
                    $message = in_array($operator, MemberAudienceFilter::RANGE_OPERATORS, true)
                        ? 'Enter exactly two comma-separated values for this operator.'
                        : 'Enter a condition value for this filter.';
                    $validator->errors()->add('filter_value', $message);
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'branch_id.required_if' => 'Please choose a branch for this audience.',
            'department_id.required_if' => 'Please choose a department.',
            'department_id.exists' => 'The selected department is not available in the chosen branch.',
            'group_id.required_if' => 'Please choose a group.',
            'group_id.exists' => 'The selected group does not belong to the chosen branch.',
        ];
    }
}
