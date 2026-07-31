<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use App\Models\EmployeePolicyAcknowledgement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePolicyAcknowledgementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EmployeePolicyAcknowledgement::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'policy_key' => ['required', 'string', 'max:120', Rule::in(['hr.attendance_geofence_policy'])],
            'policy_version' => ['required', 'integer', 'min:1', 'max:1000'],
            'acknowledgement_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $employee = Employee::find($this->integer('employee_id'));

                if (! $employee || $employee->user_id !== $this->user()?->id) {
                    $validator->errors()->add('employee_id', 'You can acknowledge policies only for your own employee profile.');
                }
            },
        ];
    }
}
