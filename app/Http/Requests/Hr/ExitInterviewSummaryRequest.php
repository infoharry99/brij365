<?php

namespace App\Http\Requests\Hr;

use App\Models\EmployeeExitInterview;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ExitInterviewSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewSummary', EmployeeExitInterview::class) === true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'department' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in(['scheduled', 'submitted', 'reviewed', 'archived'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), [
                'from',
                'to',
                'department',
                'status',
            ]);
        });
    }
}
