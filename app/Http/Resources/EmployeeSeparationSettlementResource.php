<?php

namespace App\Http\Resources;

use App\Domain\Hr\Services\EmployeeFieldVisibility;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeSeparationSettlementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();
        $employee = $this->resource->relationLoaded('employee')
            ? $this->resource->getRelation('employee')
            : null;
        $canViewCompensation = $actor instanceof User
            && $employee instanceof Employee
            && app(EmployeeFieldVisibility::class)->canViewCompensation($actor, $employee);

        return [
            'id' => $this->id,
            'settlement_number' => $this->settlement_number,
            'separation_type' => $this->separation_type,
            'status' => $this->status,
            'resignation_date' => $this->resignation_date?->toDateString(),
            'last_working_date' => $this->last_working_date?->toDateString(),
            'settlement_date' => $this->settlement_date?->toDateString(),
            'reason' => $this->reason,
            'calculation_breakdown' => $this->when($canViewCompensation, $this->calculation_breakdown ?? []),
            'clearance_blockers' => $this->clearance_blockers ?? [],
            'last_salary_amount' => $this->when($canViewCompensation, (float) $this->last_salary_amount),
            'leave_encashment_amount' => $this->when($canViewCompensation, (float) $this->leave_encashment_amount),
            'bonus_amount' => $this->when($canViewCompensation, (float) $this->bonus_amount),
            'gratuity_amount' => $this->when($canViewCompensation, (float) $this->gratuity_amount),
            'claim_payable_amount' => $this->when($canViewCompensation, (float) $this->claim_payable_amount),
            'notice_recovery_amount' => $this->when($canViewCompensation, (float) $this->notice_recovery_amount),
            'loan_recovery_amount' => $this->when($canViewCompensation, (float) $this->loan_recovery_amount),
            'asset_recovery_amount' => $this->when($canViewCompensation, (float) $this->asset_recovery_amount),
            'tax_recovery_amount' => $this->when($canViewCompensation, (float) $this->tax_recovery_amount),
            'gross_payable' => $this->when($canViewCompensation, (float) $this->gross_payable),
            'total_recoveries' => $this->when($canViewCompensation, (float) $this->total_recoveries),
            'net_payable' => $this->when($canViewCompensation, (float) $this->net_payable),
            'payment_reference' => $this->when($canViewCompensation, $this->payment_reference),
            'workflow_history' => $this->workflow_history ?? [],
            'hr_approved_at' => $this->hr_approved_at?->toISOString(),
            'finance_approved_at' => $this->finance_approved_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'employee' => $this->whenLoaded('employee', fn (): ?array => $this->employee ? [
                'id' => $this->employee->id,
                'employee_code' => $this->employee->employee_code,
                'name' => $this->employee->name,
                'designation' => $this->employee->designation,
                'department' => $this->employee->department,
                'status' => $this->employee->status,
            ] : null),
            'initiated_by' => $this->whenLoaded('initiatedBy', fn () => $this->userSummary($this->initiatedBy)),
            'hr_approved_by' => $this->whenLoaded('hrApprovedBy', fn () => $this->userSummary($this->hrApprovedBy)),
            'finance_approved_by' => $this->whenLoaded('financeApprovedBy', fn () => $this->userSummary($this->financeApprovedBy)),
            'completed_by' => $this->whenLoaded('completedBy', fn () => $this->userSummary($this->completedBy)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function userSummary($user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ] : null;
    }
}
