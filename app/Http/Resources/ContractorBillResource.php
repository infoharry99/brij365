<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractorBillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bill_number' => $this->bill_number,
            'bill_date' => $this->bill_date?->toDateString(),
            'status' => $this->status,
            'gross_amount' => (float) $this->gross_amount,
            'retention_percent' => (float) $this->retention_percent,
            'retention_amount' => (float) $this->retention_amount,
            'deduction_amount' => (float) $this->deduction_amount,
            'tax_amount' => (float) $this->tax_amount,
            'payable_amount' => (float) $this->payable_amount,
            'paid_amount' => (float) $this->paid_amount,
            'balance_amount' => (float) $this->balance_amount,
            'deductions' => $this->deductions ?? [],
            'payment_history' => $this->payment_history ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'remarks' => $this->remarks,
            'approved_at' => $this->approved_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'project' => $this->whenLoaded('project', fn (): array => [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ]),
            'vendor' => $this->whenLoaded('vendor', fn (): array => [
                'id' => $this->vendor->id,
                'vendor_code' => $this->vendor->vendor_code,
                'name' => $this->vendor->name,
            ]),
            'measurement' => $this->whenLoaded('measurement', fn (): array => [
                'id' => $this->measurement->id,
                'measurement_number' => $this->measurement->measurement_number,
                'certified_total' => (float) $this->measurement->certified_total,
            ]),
            'prepared_by' => $this->whenLoaded('preparedBy', fn (): ?array => $this->preparedBy ? [
                'id' => $this->preparedBy->id,
                'name' => $this->preparedBy->name,
                'email' => $this->preparedBy->email,
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
