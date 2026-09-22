<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\AskDataService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AskDataRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = backpack_user();

        return $user instanceof User && app(AskDataService::class)->canUse($user);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:500'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'question.required' => 'Ask a question about your church data.',
            'question.max' => 'Keep the question under 500 characters.',
            'to.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }
}
