<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePerformanceReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PerformanceReview::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'performance_cycle_id' => ['required', 'integer', Rule::exists('performance_cycles', 'id')],
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'manager_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'kpis' => ['required', 'array', 'min:1'],
            'kpis.*.name' => ['required', 'string', 'max:160'],
            'kpis.*.target' => ['nullable', 'string', 'max:255'],
            'kpis.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'kpis.*.metric' => ['nullable', 'string', 'max:80'],
            'kra_summary' => ['nullable', 'array'],
            'kra_summary.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            $companyScope = app(CompanyScopeService::class);
            $cycle = PerformanceCycle::find($this->integer('performance_cycle_id'));
            $employee = Employee::find($this->integer('employee_id'));

            if (! $user || ! $cycle || ! $employee) {
                return;
            }

            if (! $companyScope->allows($user, $cycle->company_id)) {
                $validator->errors()->add('performance_cycle_id', 'The selected performance cycle is outside your company scope.');
            }

            if (! $companyScope->allows($user, $employee->company_id)) {
                $validator->errors()->add('employee_id', 'The selected employee is outside your company scope.');
            }

            if ((int) $cycle->company_id !== (int) $employee->company_id) {
                $validator->errors()->add('employee_id', 'The employee does not belong to the selected performance cycle company.');
            }

            if ($this->filled('manager_employee_id')) {
                $manager = Employee::find($this->integer('manager_employee_id'));

                if ($manager && (int) $manager->company_id !== (int) $employee->company_id) {
                    $validator->errors()->add('manager_employee_id', 'The manager must belong to the same company as the employee.');
                }

                if ($manager && (int) $manager->id === (int) $employee->id) {
                    $validator->errors()->add('manager_employee_id', 'An employee cannot be their own performance manager.');
                }
            }
        });
    }
}
