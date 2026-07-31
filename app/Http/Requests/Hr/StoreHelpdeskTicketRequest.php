<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use App\Models\HrHelpdeskTicket;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreHelpdeskTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', HrHelpdeskTicket::class) === true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'category' => ['required', 'string', Rule::in(['payroll', 'attendance', 'leave', 'documents', 'assets', 'policy', 'other'])],
            'priority' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*.name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.url' => ['required_with:attachments', 'string', 'max:1024'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $employee = Employee::find($this->integer('employee_id'));
            $actor = $this->user();
            if (! $employee || ! $actor) {
                return;
            }
            $selfServiceOnly = $actor->hasPermission('employee.self_service')
                && ! $actor->hasPermission('helpdesk.manage')
                && ! $actor->hasPermission('hr.manage');

            if ($selfServiceOnly && (int) $employee->user_id !== (int) $actor->id) {
                $validator->errors()->add('employee_id', 'Employees can raise HR helpdesk tickets only for their own profile.');

                return;
            }
            if (! app(CompanyScopeService::class)->allows($actor, $employee->company_id)) {
                $validator->errors()->add('employee_id', 'The selected employee is not available in your company.');
            }
        }];
    }
}
