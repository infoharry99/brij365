<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use App\Models\LeaveEncashment;
use App\Models\LeaveType;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeaveEncashmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LeaveEncashment::class) === true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')],
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'requested_days' => ['required', 'numeric', 'min:0.5', 'max:365'],
            'request_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [$this->validateScopeAndPolicy(...)];
    }

    protected function validateScopeAndPolicy(Validator $validator): void
    {
        $employee = Employee::find($this->integer('employee_id'));
        $leaveType = LeaveType::find($this->integer('leave_type_id'));
        $actor = $this->user();

        if (! $employee || ! $leaveType || ! $actor) {
            return;
        }

        if (! $actor->hasPermission('leave.manage') && (int) $employee->user_id !== (int) $actor->id) {
            $validator->errors()->add('employee_id', 'Employees can request encashment only for their own profile.');
        }

        if (! app(CompanyScopeService::class)->allows($actor, $employee->company_id)) {
            $validator->errors()->add('employee_id', 'The employee does not belong to your company.');
        }

        if ((int) $leaveType->company_id !== (int) $employee->company_id) {
            $validator->errors()->add('leave_type_id', 'The leave type does not belong to the employee company.');
        }

        if (! $leaveType->encashment_enabled) {
            $validator->errors()->add('leave_type_id', 'This leave type is not eligible for encashment.');
        }
    }
}
