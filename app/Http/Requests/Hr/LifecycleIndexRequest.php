<?php

namespace App\Http\Requests\Hr;

use App\Http\Requests\Concerns\ValidatesEmployeeFilterScope;
use App\Models\EmployeeConfirmationCase;
use App\Models\EmployeeExitInterview;
use App\Models\EmployeeSeparationSettlement;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LifecycleIndexRequest extends FormRequest
{
    use ValidatesEmployeeFilterScope;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->can('viewAny', EmployeeConfirmationCase::class)
            || $user->can('viewAny', EmployeeSeparationSettlement::class)
            || $user->can('viewAny', EmployeeExitInterview::class)
        );
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'stage' => ['nullable', 'string', Rule::in(['all', 'movements', 'confirmation', 'separation', 'exit'])],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'department' => ['nullable', 'string', 'max:120'],
            'per_page' => app(PaginationPolicy::class)->defaultRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), [
                'stage',
                'employee_id',
                'department',
                'per_page',
                'page',
            ]);

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateEmployeeFilterScope(
                $validator,
                ['hr.view', 'hr.manage', 'performance.manage', 'finance.view', 'finance.approve', 'employee.self_service'],
                'employee_id',
            );
        }];
    }
}
