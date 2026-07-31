<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use App\Support\PaginationPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeMovementIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee instanceof Employee
            && $this->user()?->can('view', $employee) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in(['pending', 'approved', 'cancelled'])],
            'movement_type' => ['sometimes', 'string', Rule::in(['transfer', 'promotion', 'department_change', 'reporting_change', 'salary_change', 'status_change', 'grade_change'])],
            'per_page' => app(PaginationPolicy::class)->defaultRule(),
        ];
    }
}
