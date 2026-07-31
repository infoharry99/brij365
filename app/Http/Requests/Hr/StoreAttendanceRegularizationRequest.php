<?php

namespace App\Http\Requests\Hr;

use App\Models\AttendanceRegularizationRequest;
use App\Models\Employee;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAttendanceRegularizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AttendanceRegularizationRequest::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'requested_check_in_at' => ['required', 'date'],
            'requested_check_out_at' => ['required', 'date', 'after:requested_check_in_at'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
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

            if (! $user?->hasPermission('attendance.manage') && $employee->user_id !== $user?->id) {
                $validator->errors()->add('employee_id', 'You can submit attendance regularization only for your own employee profile.');
            }

            $workDate = $this->date('work_date')->toDateString();
            if ($this->date('requested_check_in_at')->toDateString() !== $workDate) {
                $validator->errors()->add('requested_check_in_at', 'Requested check-in must fall on the work date.');
            }

            $openRequestExists = AttendanceRegularizationRequest::query()
                ->where('employee_id', $employee->id)
                ->where('work_date', $workDate)
                ->where('status', 'submitted')
                ->exists();

            if ($openRequestExists) {
                $validator->errors()->add('work_date', 'An attendance regularization request is already pending for this date.');
            }
        });
    }
}
