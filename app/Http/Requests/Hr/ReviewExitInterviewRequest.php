<?php

namespace App\Http\Requests\Hr;

use App\Models\EmployeeExitInterview;
use Illuminate\Foundation\Http\FormRequest;

class ReviewExitInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $exitInterview = $this->route('employeeExitInterview');

        return $exitInterview instanceof EmployeeExitInterview
            && $this->user()?->can('review', $exitInterview) === true;
    }

    public function rules(): array
    {
        return [
            'hr_review_notes' => ['required', 'string', 'max:3000'],
            'action_items' => ['nullable', 'array', 'max:20'],
            'action_items.*.owner' => ['required_with:action_items', 'string', 'max:120'],
            'action_items.*.action' => ['required_with:action_items', 'string', 'max:255'],
            'action_items.*.due_on' => ['nullable', 'date'],
            'action_items.*.status' => ['nullable', 'string', 'max:40'],
        ];
    }
}
