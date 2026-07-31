<?php

namespace App\Http\Requests\Hr;

use App\Http\Requests\Concerns\ValidatesEmployeeFilterScope;
use App\Models\EmployeeConfirmationCase;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConfirmationCaseIndexRequest extends FormRequest
{
    use ValidatesEmployeeFilterScope;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', EmployeeConfirmationCase::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'manager_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'department' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in(['due', 'manager_recommended', 'confirmed', 'extended', 'rejected'])],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date', 'after_or_equal:due_from'],
            'per_page' => app(PaginationPolicy::class)->defaultRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), [
                    'employee_id',
                    'manager_employee_id',
                    'department',
                    'status',
                    'due_from',
                    'due_to',
                    'per_page',
                    'page',
                ]);

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateEmployeeFilterScope($validator, ['hr.view', 'hr.manage', 'performance.manage'], 'employee_id');
                $this->validateEmployeeFilterScope($validator, ['hr.view', 'hr.manage', 'performance.manage'], 'manager_employee_id');
            },
        ];
    }
}
