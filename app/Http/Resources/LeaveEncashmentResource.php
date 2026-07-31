<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveEncashmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'encashment_number' => $this->encashment_number,
            'period_year' => $this->period_year,
            'status' => $this->status,
            'requested_days' => (float) $this->requested_days,
            'approved_days' => (float) $this->approved_days,
            'daily_rate' => (float) $this->daily_rate,
            'gross_amount' => (float) $this->gross_amount,
            'tax_amount' => (float) $this->tax_amount,
            'net_amount' => (float) $this->net_amount,
            'calculation_snapshot' => $this->calculation_snapshot ?? [],
            'request_note' => $this->request_note,
            'decision_note' => $this->decision_note,
            'payroll_reference' => $this->payroll_reference,
            'workflow_history' => $this->workflow_history ?? [],
            'approved_at' => $this->approved_at?->toISOString(),
            'payroll_marked_at' => $this->payroll_marked_at?->toISOString(),
            'employee' => $this->whenLoaded('employee', fn (): ?array => $this->employee ? [
                'id' => $this->employee->id,
                'employee_code' => $this->employee->employee_code,
                'name' => $this->employee->name,
                'department' => $this->employee->department,
                'designation' => $this->employee->designation,
            ] : null),
            'leave_type' => $this->whenLoaded('leaveType', fn (): ?array => $this->leaveType ? [
                'id' => $this->leaveType->id,
                'code' => $this->leaveType->code,
                'name' => $this->leaveType->name,
                'encashment_enabled' => $this->leaveType->encashment_enabled,
            ] : null),
            'requested_by' => $this->whenLoaded('requestedBy', fn () => $this->userSummary($this->requestedBy)),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->userSummary($this->approvedBy)),
            'payroll_marked_by' => $this->whenLoaded('payrollMarkedBy', fn () => $this->userSummary($this->payrollMarkedBy)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function userSummary($user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email] : null;
    }
}
