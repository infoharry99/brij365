<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use App\Models\EmployeeExitInterview;
use App\Models\EmployeeSeparationSettlement;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExitInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EmployeeExitInterview::class) === true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'employee_separation_settlement_id' => ['nullable', 'integer', Rule::exists('employee_separation_settlements', 'id')],
            'interview_due_on' => ['required', 'date'],
            'questionnaire_template' => ['nullable', 'array', 'max:20'],
            'questionnaire_template.*.key' => ['required_with:questionnaire_template', 'string', 'max:80'],
            'questionnaire_template.*.label' => ['required_with:questionnaire_template', 'string', 'max:255'],
            'questionnaire_template.*.type' => ['nullable', 'string', Rule::in(['text', 'rating', 'choice', 'multi_choice'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [$this->validateCompanyAndDuplicate(...)];
    }

    protected function validateCompanyAndDuplicate(Validator $validator): void
    {
        $employee = Employee::find($this->integer('employee_id'));
        $actor = $this->user();

        if (! $employee) {
            return;
        }

        if (! $actor || ! app(CompanyScopeService::class)->allows($actor, $employee->company_id)) {
            $validator->errors()->add('employee_id', 'The employee does not belong to your company.');
        }

        if (EmployeeExitInterview::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['scheduled', 'submitted'])
            ->exists()) {
            $validator->errors()->add('employee_id', 'An active exit interview already exists for this employee.');
        }

        if ($this->filled('employee_separation_settlement_id')) {
            $settlement = EmployeeSeparationSettlement::find($this->integer('employee_separation_settlement_id'));

            if ($settlement && (int) $settlement->employee_id !== (int) $employee->id) {
                $validator->errors()->add('employee_separation_settlement_id', 'The selected settlement does not belong to this employee.');
            }

            if ($settlement && (int) $settlement->company_id !== (int) $employee->company_id) {
                $validator->errors()->add('employee_separation_settlement_id', 'The selected settlement does not belong to the employee company.');
            }
        }
    }
}
