<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GstEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entry_number' => $this->entry_number,
            'period_year' => $this->period_year,
            'period_month' => $this->period_month,
            'document_date' => $this->document_date?->toDateString(),
            'document_number' => $this->document_number,
            'party_name' => $this->party_name,
            'party_gstin' => $this->party_gstin,
            'place_of_supply_state' => $this->place_of_supply_state,
            'transaction_type' => $this->transaction_type,
            'hsn_sac' => $this->hsn_sac,
            'tax_rate' => (float) $this->tax_rate,
            'taxable_amount' => (float) $this->taxable_amount,
            'cgst_amount' => (float) $this->cgst_amount,
            'sgst_amount' => (float) $this->sgst_amount,
            'igst_amount' => (float) $this->igst_amount,
            'cess_amount' => (float) $this->cess_amount,
            'total_tax_amount' => (float) $this->total_tax_amount,
            'status' => $this->status,
            'metadata' => $this->metadata ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'approved_at' => $this->approved_at?->toISOString(),
            'project' => $this->whenLoaded('project', fn (): ?array => $this->project ? [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ] : null),
            'created_by' => $this->whenLoaded('createdBy', fn (): ?array => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'email' => $this->createdBy->email,
            ] : null),
            'approved_by' => $this->whenLoaded('approvedBy', fn (): ?array => $this->approvedBy ? [
                'id' => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
                'email' => $this->approvedBy->email,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
