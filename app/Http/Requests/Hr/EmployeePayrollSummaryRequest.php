<?php

namespace App\Http\Requests\Hr;

use App\Domain\Hr\Services\EmployeeFieldVisibility;
use App\Models\Employee;
use App\Services\Security\CompanyScopeService;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EmployeePayrollSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $employee = $this->route('employee');

        if (! $user || ! $employee instanceof Employee) {
            return false;
        }

        if (! app(EmployeeFieldVisibility::class)->canViewCompensation($user, $employee)) {
            return false;
        }

        return app(CompanyScopeService::class)->allows($user, $employee->company_id);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), []);
            },
        ];
    }
}
