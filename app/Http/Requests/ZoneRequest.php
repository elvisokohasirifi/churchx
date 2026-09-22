<?php

namespace App\Http\Requests;

use App\Models\Zone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ZoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $zone = $this->route('id') ? Zone::query()->find($this->route('id')) : null;

        return Gate::forUser(backpack_user())->allows($zone ? 'update' : 'create', $zone ?? Zone::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $zoneId = $this->route('id');
        $leaderIdRules = ['nullable', 'uuid'];
        $leaderIdRules[] = $zoneId
            ? Rule::exists('zone_leaders', 'id')->where('zone_id', $zoneId)
            : 'prohibited';

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('zones')->ignore($this->route('id'))],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'leaders' => ['nullable', 'array', 'max:50'],
            'leaders.*.id' => $leaderIdRules,
            'leaders.*.user_id' => ['required', 'uuid', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'leaders.*.leadership_title_id' => ['required', 'uuid', 'exists:leadership_titles,id'],
            'leaders.*.start_date' => ['required', 'date'],
            'leaders.*.end_date' => ['nullable', 'date', 'after_or_equal:leaders.*.start_date'],
            'leaders.*.is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $appointments = [];

            foreach ((array) $this->input('leaders', []) as $index => $leader) {
                $userId = data_get($leader, 'user_id');
                $titleId = data_get($leader, 'leadership_title_id');
                $startDate = data_get($leader, 'start_date');

                if (! is_string($userId) || ! is_string($titleId) || ! is_string($startDate)) {
                    continue;
                }

                $appointment = $userId.'|'.$titleId.'|'.$startDate;
                if (isset($appointments[$appointment])) {
                    $validator->errors()->add(
                        "leaders.{$index}.user_id",
                        'The same leader, title, and start date may only be added once.',
                    );
                }

                $appointments[$appointment] = true;
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'leaders.*.user_id' => 'leader',
            'leaders.*.leadership_title_id' => 'leadership title',
            'leaders.*.start_date' => 'leader start date',
            'leaders.*.end_date' => 'leader end date',
        ];
    }
}
