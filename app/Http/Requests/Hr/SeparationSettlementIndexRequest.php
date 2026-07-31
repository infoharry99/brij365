<?php

namespace App\Http\Requests\Hr;

use App\Http\Requests\Concerns\ValidatesEmployeeFilterScope;
use App\Models\EmployeeSeparationSettlement;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SeparationSettlementIndexRequest extends FormRequest
{
    use ValidatesEmployeeFilterScope;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', EmployeeSeparationSettlement::class) === true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'status' => ['nullable', 'string', Rule::in(['initiated', 'hr_approved', 'finance_approved', 'completed'])],
            'separation_type' => ['nullable', 'string', Rule::in(['resignation', 'termination', 'retirement', 'contract_end'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
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
                    'status',
                    'separation_type',
                    'from',
                    'to',
                    'per_page',
                    'page',
                ]);

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateEmployeeFilterScope($validator, ['hr.view', 'hr.manage', 'finance.view', 'finance.approve']);
            },
        ];
    }
}
