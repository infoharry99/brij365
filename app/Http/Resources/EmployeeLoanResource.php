<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeLoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_number' => $this->loan_number,
            'loan_type' => $this->loan_type,
            'status' => $this->status,
            'principal_amount' => (float) $this->principal_amount,
            'approved_amount' => (float) $this->approved_amount,
            'installment_months' => $this->installment_months,
            'monthly_installment' => (float) $this->monthly_installment,
            'requested_on' => $this->requested_on?->toDateString(),
            'repayment_starts_on' => $this->repayment_starts_on?->toDateString(),
            'purpose' => $this->purpose,
            'decision_note' => $this->decision_note,
            'workflow_history' => $this->workflow_history ?? [],
            'approved_at' => $this->approved_at?->toISOString(),
            'disbursed_at' => $this->disbursed_at?->toISOString(),
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'employee_code' => $this->employee->employee_code,
                'name' => $this->employee->name,
                'department' => $this->employee->department,
            ]),
            'requested_by' => $this->whenLoaded('requestedBy', fn () => $this->requestedBy ? ['name' => $this->requestedBy->name, 'email' => $this->requestedBy->email] : null),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy ? ['name' => $this->approvedBy->name, 'email' => $this->approvedBy->email] : null),
            'disbursed_by' => $this->whenLoaded('disbursedBy', fn () => $this->disbursedBy ? ['name' => $this->disbursedBy->name, 'email' => $this->disbursedBy->email] : null),
        ];
    }
}
