<?php

namespace App\Http\Requests\Hr;

use App\Models\AttendanceRegularizationRequest;
use App\Models\Employee;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AttendanceRegularizationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AttendanceRegularizationRequest::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['nullable', Rule::in(['submitted', 'approved', 'rejected'])],
            'per_page' => app(PaginationPolicy::class)->defaultRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), [
                'employee_id',
                'status',
                'per_page',
                'page',
            ]);

            if ($validator->errors()->isNotEmpty() || ! $this->filled('employee_id')) {
                return;
            }

            $employee = Employee::find($this->integer('employee_id'));
            if (! $employee) {
                return;
            }

            $user = $this->user();
            if (! $user || ! app(CompanyScopeService::class)->allows($user, $employee->company_id)) {
                $validator->errors()->add('employee_id', 'The selected employee is outside your company scope.');
            }

            if (
                ! $user?->hasPermission('attendance.manage')
                && ! $user?->hasPermission('attendance.approve')
                && $employee->user_id !== $user?->id
            ) {
                $validator->errors()->add('employee_id', 'You can view attendance regularizations only for your own employee profile.');
            }
        });
    }
}
