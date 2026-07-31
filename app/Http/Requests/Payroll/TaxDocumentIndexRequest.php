<?php

namespace App\Http\Requests\Payroll;

use App\Models\Employee;
use App\Models\EmployeeTaxDocument;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TaxDocumentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', EmployeeTaxDocument::class) === true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'financial_year' => ['nullable', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'status' => ['nullable', 'string', Rule::in(['generated', 'issued', 'acknowledged', 'revoked'])],
            'document_type' => ['nullable', 'string', Rule::in(['form_16'])],
            'per_page' => app(PaginationPolicy::class)->largeRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected(
                    $validator,
                    $this->query(),
                    ['employee_id', 'financial_year', 'status', 'document_type', 'per_page', 'page'],
                );

                if ($validator->errors()->isNotEmpty() || ! $this->filled('employee_id')) {
                    return;
                }

                $user = $this->user();
                $employee = Employee::query()->find($this->integer('employee_id'));

                if (! $user || ! $employee) {
                    return;
                }

                if ($this->isSelfServiceOnlyUser() && $employee->user_id !== $user->id) {
                    $validator->errors()->add('employee_id', 'The selected employee is outside your tax document scope.');

                    return;
                }

                if (! app(CompanyScopeService::class)->allows($user, $employee->company_id)) {
                    $validator->errors()->add('employee_id', 'The selected employee is not available for your company.');
                }
            },
        ];
    }

    private function isSelfServiceOnlyUser(): bool
    {
        $user = $this->user();

        return $user?->hasPermission('employee.self_service') === true
            && ! $user->hasPermission('payroll.view')
            && ! $user->hasPermission('payroll.manage')
            && ! $user->hasPermission('payroll.approve')
            && ! $user->hasPermission('compliance.view')
            && ! $user->hasPermission('compliance.manage');
    }
}
