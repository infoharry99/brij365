<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseClaimResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'claim_number' => $this->claim_number,
            'claim_type' => $this->claim_type,
            'status' => $this->status,
            'claim_date' => $this->claim_date?->toDateString(),
            'amount' => (float) $this->amount,
            'approved_amount' => (float) $this->approved_amount,
            'currency' => $this->currency,
            'description' => $this->description,
            'attachments' => $this->attachments ?? [],
            'decision_note' => $this->decision_note,
            'workflow_history' => $this->workflow_history ?? [],
            'approved_at' => $this->approved_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'company' => $this->whenLoaded('company', fn (): ?array => $this->company ? [
                'id' => $this->company->id,
                'code' => $this->company->code,
                'name' => $this->company->name,
            ] : null),
            'employee' => $this->whenLoaded('employee', fn (): ?array => $this->employee ? [
                'id' => $this->employee->id,
                'employee_code' => $this->employee->employee_code,
                'name' => $this->employee->name,
                'department' => $this->employee->department,
                'designation' => $this->employee->designation,
            ] : null),
            'requested_by' => $this->whenLoaded('requestedBy', fn (): ?array => $this->requestedBy ? [
                'id' => $this->requestedBy->id,
                'name' => $this->requestedBy->name,
                'email' => $this->requestedBy->email,
            ] : null),
            'approved_by' => $this->whenLoaded('approvedBy', fn (): ?array => $this->approvedBy ? [
                'id' => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
                'email' => $this->approvedBy->email,
            ] : null),
            'paid_by' => $this->whenLoaded('paidBy', fn (): ?array => $this->paidBy ? [
                'id' => $this->paidBy->id,
                'name' => $this->paidBy->name,
                'email' => $this->paidBy->email,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
