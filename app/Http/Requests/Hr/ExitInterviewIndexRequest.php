<?php

namespace App\Http\Requests\Hr;

use App\Http\Requests\Concerns\ValidatesEmployeeFilterScope;
use App\Models\EmployeeExitInterview;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ExitInterviewIndexRequest extends FormRequest
{
    use ValidatesEmployeeFilterScope;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', EmployeeExitInterview::class) === true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'status' => ['nullable', 'string', Rule::in(['scheduled', 'submitted', 'reviewed', 'archived'])],
            'separation_reason' => ['nullable', 'string', Rule::in($this->reasonOptions())],
            'rehire_recommendation' => ['nullable', 'string', Rule::in(['yes', 'no', 'conditional'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => app(PaginationPolicy::class)->largeRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), [
                    'employee_id',
                    'status',
                    'separation_reason',
                    'rehire_recommendation',
                    'from',
                    'to',
                    'per_page',
                    'page',
                ]);

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateEmployeeFilterScope($validator, ['hr.view', 'hr.manage']);
            },
        ];
    }

    private function reasonOptions(): array
    {
        return ['career_growth', 'compensation', 'relocation', 'manager_issue', 'work_environment', 'health', 'retirement', 'contract_end', 'personal', 'other'];
    }
}
