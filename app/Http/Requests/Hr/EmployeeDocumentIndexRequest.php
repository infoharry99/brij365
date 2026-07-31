<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use App\Support\PaginationPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeDocumentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee instanceof Employee
            && ($this->user()?->can('view', $employee) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['submitted', 'approved', 'rejected', 'archived'])],
            'current_only' => ['nullable', 'boolean'],
            'expires_within_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'per_page' => app(PaginationPolicy::class)->defaultRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
