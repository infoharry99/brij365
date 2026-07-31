<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PerformanceReviewIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PerformanceReview::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cycle_id' => ['nullable', 'integer', Rule::exists('performance_cycles', 'id')],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'department' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'self_submitted', 'manager_submitted', 'closed'])],
            'pip_required' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => app(PaginationPolicy::class)->largeRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), [
                'cycle_id',
                'employee_id',
                'department',
                'status',
                'pip_required',
                'from',
                'to',
                'per_page',
                'page',
            ]);

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateCycleScope($validator);
            $this->validateEmployeeScope($validator);
        });
    }

    private function validateCycleScope(Validator $validator): void
    {
        if (! $this->filled('cycle_id')) {
            return;
        }

        $cycle = PerformanceCycle::find($this->integer('cycle_id'));
        $user = $this->user();

        if ($cycle && (! $user || ! app(CompanyScopeService::class)->allows($user, $cycle->company_id))) {
            $validator->errors()->add('cycle_id', 'The selected performance cycle is outside your company scope.');
        }
    }

    private function validateEmployeeScope(Validator $validator): void
    {
        if (! $this->filled('employee_id')) {
            return;
        }

        $employee = Employee::find($this->integer('employee_id'));
        if (! $employee) {
            return;
        }

        $user = $this->user();
        $actorEmployee = $user?->employee;

        if (! $user || ! app(CompanyScopeService::class)->allows($user, $employee->company_id)) {
            $validator->errors()->add('employee_id', 'The selected employee is outside your company scope.');

            return;
        }

        if ($user?->hasPermission('*') || $user?->hasPermission('hr.manage') || $user?->hasPermission('performance.view') || $user?->hasPermission('performance.approve')) {
            return;
        }

        if ($user?->hasPermission('performance.manage')) {
            if ($employee->id === $actorEmployee?->id || $employee->manager_employee_id === $actorEmployee?->id) {
                return;
            }

            $validator->errors()->add('employee_id', 'You can filter performance reviews only for yourself or direct reports.');

            return;
        }

        if ($employee->id !== $actorEmployee?->id) {
            $validator->errors()->add('employee_id', 'You can filter performance reviews only for your own employee profile.');
        }
    }
}
