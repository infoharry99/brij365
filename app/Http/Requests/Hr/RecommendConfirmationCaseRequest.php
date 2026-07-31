<?php

namespace App\Http\Requests\Hr;

use App\Models\EmployeeConfirmationCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecommendConfirmationCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $case = $this->route('employeeConfirmationCase');

        return $case instanceof EmployeeConfirmationCase
            && $this->user()?->can('recommend', $case) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'manager_recommendation' => ['required', 'string', Rule::in(['confirm', 'extend', 'reject'])],
            'manager_comments' => ['required', 'string', 'max:3000'],
            'review_scores' => ['nullable', 'array'],
            'review_scores.performance' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'review_scores.behaviour' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'review_scores.attendance' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'review_scores.culture_fit' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'review_scores.training_completion' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'review_scores.policy_compliance' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'review_scores.manager_recommendation' => ['nullable', 'numeric', 'min:1', 'max:5'],
        ];
    }
}
