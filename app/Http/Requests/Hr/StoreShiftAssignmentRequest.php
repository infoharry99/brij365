<?php

namespace App\Http\Requests\Hr;

use App\Models\AttendanceShift;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreShiftAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EmployeeShiftAssignment::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'attendance_shift_id' => ['required', 'integer', Rule::exists('attendance_shifts', 'id')],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            $employee = Employee::find($this->integer('employee_id'));
            $shift = AttendanceShift::find($this->integer('attendance_shift_id'));

            if (! $user || ! $employee || ! $shift) {
                return;
            }

            $companyId = $this->filled('company_id')
                ? $this->integer('company_id')
                : (int) $employee->company_id;

            if (! app(CompanyScopeService::class)->allows($user, $companyId)) {
                $validator->errors()->add('company_id', 'The selected company is outside your company scope.');
            }

            if ((int) $employee->company_id !== $companyId) {
                $validator->errors()->add('employee_id', 'The selected employee does not belong to the assignment company.');
            }

            if ((int) $shift->company_id !== $companyId) {
                $validator->errors()->add('attendance_shift_id', 'The selected shift does not belong to the assignment company.');
            }

            if ($employee->status !== 'active') {
                $validator->errors()->add('employee_id', 'Only active employees may receive a shift assignment.');
            }

            if (! $shift->is_active) {
                $validator->errors()->add('attendance_shift_id', 'Only active shifts may be assigned.');
            }

            $effectiveFrom = $this->date('effective_from')->toDateString();
            $effectiveTo = $this->filled('effective_to')
                ? $this->date('effective_to')->toDateString()
                : '9999-12-31';

            $exactAssignmentExists = EmployeeShiftAssignment::query()
                ->where('employee_id', $employee->id)
                ->where('attendance_shift_id', $shift->id)
                ->where('is_active', true)
                ->whereDate('effective_from', $effectiveFrom)
                ->where(function ($query): void {
                    if ($this->filled('effective_to')) {
                        $query->whereDate('effective_to', $this->date('effective_to')->toDateString());

                        return;
                    }

                    $query->whereNull('effective_to');
                })
                ->exists();

            // Repeating the same command is intentionally idempotent. The manager
            // returns the existing row; only a materially different overlap is invalid.
            if ($exactAssignmentExists) {
                return;
            }

            $overlapExists = EmployeeShiftAssignment::query()
                ->where('employee_id', $employee->id)
                ->where('is_active', true)
                ->whereDate('effective_from', '<=', $effectiveTo)
                ->where(function ($query) use ($effectiveFrom): void {
                    $query->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $effectiveFrom);
                })
                ->exists();

            if ($overlapExists) {
                $validator->errors()->add('effective_from', 'This employee already has an active shift assignment that overlaps the selected period.');
            }
        }];
    }
}
