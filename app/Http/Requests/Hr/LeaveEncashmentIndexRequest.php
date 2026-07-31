<?php

namespace App\Http\Requests\Hr;

use App\Http\Requests\Concerns\ValidatesEmployeeFilterScope;
use App\Models\LeaveEncashment;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LeaveEncashmentIndexRequest extends FormRequest
{
    use ValidatesEmployeeFilterScope;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', LeaveEncashment::class) === true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'period_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'status' => ['nullable', 'string', Rule::in(['submitted', 'approved', 'rejected', 'payroll_marked'])],
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
                    'period_year',
                    'status',
                    'per_page',
                    'page',
                ]);

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateEmployeeFilterScope($validator, ['leave.view', 'leave.manage', 'leave.approve', 'payroll.view', 'payroll.manage']);
            },
        ];
    }
}
