<?php

namespace App\Http\Requests\Payroll;

use App\Models\EmployeeTaxProfile;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class EmployeeTaxProfileReviewIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', EmployeeTaxProfile::class) === true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['draft', 'submitted', 'verified', 'locked'])],
            'financial_year' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'per_page' => app(PaginationPolicy::class)->largeRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            app(QueryFilterPolicy::class)->rejectUnexpected(
                $validator,
                $this->query(),
                ['status', 'financial_year', 'employee_id', 'per_page', 'page'],
            );
        }];
    }
}
