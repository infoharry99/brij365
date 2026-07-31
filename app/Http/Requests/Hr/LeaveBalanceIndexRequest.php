<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LeaveBalanceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', LeaveRequest::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'period_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'per_page' => app(PaginationPolicy::class)->defaultRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), [
                'employee_id',
                'period_year',
                'per_page',
                'page',
            ]);

            if ($validator->errors()->isNotEmpty() || ! $this->filled('employee_id')) {
                return;
            }

            $employee = Employee::find($this->integer('employee_id'));
            if (! $employee) {
                return;
            }

            $user = $this->user();
            if (! $user || ! app(CompanyScopeService::class)->allows($user, $employee->company_id)) {
                $validator->errors()->add('employee_id', 'The selected employee is outside your company scope.');
            }

            if (
                ! $user?->hasPermission('leave.manage')
                && ! $user?->hasPermission('leave.approve')
                && $employee->user_id !== $user?->id
            ) {
                $validator->errors()->add('employee_id', 'You can view leave balances only for your own employee profile.');
            }
        });
    }
}
