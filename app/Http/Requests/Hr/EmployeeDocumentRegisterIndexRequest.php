<?php

namespace App\Http\Requests\Hr;

use App\Models\DocumentCategory;
use App\Models\Employee;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmployeeDocumentRegisterIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Employee::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'document_category_id' => ['nullable', 'integer', Rule::exists('document_categories', 'id')],
            'status' => ['nullable', Rule::in(['submitted', 'approved', 'rejected', 'archived'])],
            'current_only' => ['nullable', 'boolean'],
            'expires_within_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => app(PaginationPolicy::class)->defaultRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), [
                    'employee_id',
                    'document_category_id',
                    'status',
                    'current_only',
                    'expires_within_days',
                    'search',
                    'per_page',
                    'page',
                ]);

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateEmployeeScope($validator);
                $this->validateCategoryScope($validator);
            },
        ];
    }

    private function validateEmployeeScope(Validator $validator): void
    {
        if (! $this->filled('employee_id')) {
            return;
        }

        $employee = Employee::find($this->integer('employee_id'));

        if ($employee && ! app(CompanyScopeService::class)->allows($this->user(), $employee->company_id)) {
            $validator->errors()->add('employee_id', 'The selected employee is outside your company scope.');
        }
    }

    private function validateCategoryScope(Validator $validator): void
    {
        if (! $this->filled('document_category_id')) {
            return;
        }

        $category = DocumentCategory::find($this->integer('document_category_id'));

        if (! $category) {
            return;
        }

        if ($category->owner_type !== 'employee') {
            $validator->errors()->add('document_category_id', 'The selected document category is not an employee document category.');

            return;
        }

        if (
            $category->company_id !== null
            && ! app(CompanyScopeService::class)->allows($this->user(), $category->company_id)
        ) {
            $validator->errors()->add('document_category_id', 'The selected document category is outside your company scope.');
        }
    }
}
