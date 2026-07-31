<?php

namespace App\Http\Requests\Hr;

use App\Models\AttendanceRoster;
use App\Models\AttendancePeriodLock;
use App\Models\Employee;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AttendanceRosterIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        if ($this->query('view', 'rosters') === 'periods') {
            return $user->can('viewAny', AttendancePeriodLock::class);
        }

        return $user->can('viewAny', AttendanceRoster::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'view' => ['nullable', Rule::in(['rosters', 'rotations', 'swaps', 'periods'])],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'status' => ['nullable', Rule::in([
                'draft', 'published', 'locked', 'cancelled',
                'active', 'paused',
                'submitted', 'approved', 'rejected',
                'finalized', 'reopened',
            ])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => app(PaginationPolicy::class)->defaultRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), [
                'view', 'employee_id', 'status', 'date_from', 'date_to', 'per_page', 'page',
            ]);

            if ($validator->errors()->isNotEmpty() || ! $this->filled('employee_id')) {
                return;
            }

            $employee = Employee::find($this->integer('employee_id'));
            $user = $this->user();

            if (! $employee || ! $user || ! app(CompanyScopeService::class)->allows($user, $employee->company_id)) {
                $validator->errors()->add('employee_id', 'The selected employee is outside your company scope.');

                return;
            }

            if (
                ! $user->hasPermission('attendance.manage')
                && ! $user->hasPermission('attendance.approve')
                && (int) $employee->user_id !== (int) $user->id
            ) {
                $validator->errors()->add('employee_id', 'You can view roster information only for your own employee profile.');
            }
        }];
    }
}
