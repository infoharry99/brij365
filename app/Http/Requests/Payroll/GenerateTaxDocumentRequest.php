<?php

namespace App\Http\Requests\Payroll;

use App\Models\Employee;
use App\Models\EmployeeTaxDocument;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GenerateTaxDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EmployeeTaxDocument::class) === true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'financial_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'document_type' => ['nullable', 'string', Rule::in(['form_16'])],
            'force_new_version' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [$this->validateEmployeeScope(...)];
    }

    protected function validateEmployeeScope(Validator $validator): void
    {
        $employee = Employee::find($this->integer('employee_id'));
        $actor = $this->user();

        if (! $employee || ! $actor) {
            return;
        }

        if (! app(CompanyScopeService::class)->allows($actor, $employee->company_id)) {
            $validator->errors()->add('employee_id', 'The employee does not belong to your company.');
        }
    }
}
