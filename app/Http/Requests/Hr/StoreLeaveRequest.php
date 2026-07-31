<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LeaveRequest::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'supporting_document_id' => ['nullable', 'integer', 'exists:managed_documents,id'],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'duration_unit' => ['required', Rule::in(['full_day', 'half_day'])],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $employee = Employee::find($this->integer('employee_id'));
            $leaveType = LeaveType::find($this->integer('leave_type_id'));

            if (! $employee || ! $leaveType) {
                return;
            }

            $user = $this->user();
            if (! $user || ! app(CompanyScopeService::class)->allows($user, $employee->company_id)) {
                $validator->errors()->add('employee_id', 'The selected employee is outside your company scope.');
            }

            if (! $user?->hasPermission('leave.manage') && $employee->user_id !== $user?->id) {
                $validator->errors()->add('employee_id', 'You can submit leave only for your own employee profile.');
            }

            if ($leaveType->company_id !== $employee->company_id || ! $leaveType->is_active) {
                $validator->errors()->add('leave_type_id', 'The selected leave type is not valid for this employee.');
            }

            if ($this->input('duration_unit') === 'half_day') {
                if (! $leaveType->allows_half_day) {
                    $validator->errors()->add('duration_unit', 'This leave type does not allow half-day requests.');
                }

                if ($this->date('starts_on')->toDateString() !== $this->date('ends_on')->toDateString()) {
                    $validator->errors()->add('duration_unit', 'Half-day leave must start and end on the same date.');
                }
            }

            if ($leaveType->requires_document && ! $this->filled('supporting_document_id')) {
                $validator->errors()->add('supporting_document_id', 'A supporting document is required for this leave type.');
            }

            $overlapExists = LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->whereIn('status', ['submitted', 'approved'])
                ->whereDate('starts_on', '<=', $this->date('ends_on')->toDateString())
                ->whereDate('ends_on', '>=', $this->date('starts_on')->toDateString())
                ->exists();

            if ($overlapExists) {
                $validator->errors()->add('starts_on', 'A submitted or approved leave request already overlaps this date range.');
            }
        });
    }
}
