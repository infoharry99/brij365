<?php

namespace App\Http\Requests\Hr;

use App\Models\AttendanceShift;
use App\Models\AttendanceRotationRule;
use App\Models\Employee;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAttendanceRotationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AttendanceRotationRule::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'name' => ['required', 'string', 'max:160'],
            'anchor_date' => ['required', 'date'],
            'cycle_days' => ['required', 'integer', 'min:1', 'max:31'],
            'pattern' => ['required', 'array', 'min:1', 'max:31'],
            'pattern.*' => ['required', 'array:type,attendance_shift_id'],
            'pattern.*.type' => ['required', Rule::in(['shift', 'off', 'holiday'])],
            'pattern.*.attendance_shift_id' => ['nullable', 'integer', Rule::exists('attendance_shifts', 'id')],
            'generation_horizon_days' => ['required', 'integer', 'min:1', 'max:366'],
            'status' => ['sometimes', Rule::in(['active', 'paused'])],
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
            if (! $user || ! $employee) {
                return;
            }

            $companyId = $this->filled('company_id')
                ? $this->integer('company_id')
                : (int) $employee->company_id;

            if (! app(CompanyScopeService::class)->allows($user, $companyId)) {
                $validator->errors()->add('company_id', 'The selected company is outside your company scope.');
            }

            if ((int) $employee->company_id !== $companyId) {
                $validator->errors()->add('employee_id', 'The selected employee does not belong to the rotation company.');
            }

            if ($employee->status !== 'active') {
                $validator->errors()->add('employee_id', 'Only active employees may receive a rotation rule.');
            }

            $pattern = $this->input('pattern', []);
            if (count($pattern) !== $this->integer('cycle_days')) {
                $validator->errors()->add('pattern', 'The rotation pattern must define exactly one entry for each cycle day.');
            }

            foreach ($pattern as $index => $item) {
                $type = is_array($item) ? ($item['type'] ?? null) : null;
                $shiftId = is_array($item) ? ($item['attendance_shift_id'] ?? null) : null;
                $field = "pattern.{$index}.attendance_shift_id";

                if ($type === 'shift' && ! $shiftId) {
                    $validator->errors()->add($field, 'A shift is required for each working rotation day.');

                    continue;
                }

                if ($type !== 'shift' && $shiftId) {
                    $validator->errors()->add($field, 'Off-day and holiday rotation entries cannot carry a working shift.');

                    continue;
                }

                if (! $shiftId) {
                    continue;
                }

                $shift = AttendanceShift::find((int) $shiftId);
                if ($shift && ((int) $shift->company_id !== $companyId || ! $shift->is_active)) {
                    $validator->errors()->add($field, 'Each rotation shift must be active and belong to the rotation company.');
                }
            }
        }];
    }
}
