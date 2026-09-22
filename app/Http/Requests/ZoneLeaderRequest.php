<?php

namespace App\Http\Requests;

use App\PermissionCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ZoneLeaderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::forUser(backpack_user())->allows(PermissionCode::BranchesManage->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $appointmentId = $this->route('id');

        return [
            'zone_id' => ['required', 'uuid', Rule::exists('zones', 'id')->whereNull('deleted_at')],
            'user_id' => ['required', 'uuid', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'leadership_title_id' => ['required', 'uuid', 'exists:leadership_titles,id'],
            'start_date' => [
                'required',
                'date',
                Rule::unique('zone_leaders')->where(fn ($query) => $query
                    ->where('zone_id', $this->input('zone_id'))
                    ->where('user_id', $this->input('user_id'))
                    ->where('leadership_title_id', $this->input('leadership_title_id')))
                    ->ignore($appointmentId),
            ],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
