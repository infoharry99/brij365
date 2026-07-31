<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GstReturnPeriodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_number' => $this->return_number,
            'period_year' => $this->period_year,
            'period_month' => $this->period_month,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'status' => $this->status,
            'entry_count' => $this->entry_count,
            'output_taxable_total' => (float) $this->output_taxable_total,
            'output_tax_total' => (float) $this->output_tax_total,
            'input_taxable_total' => (float) $this->input_taxable_total,
            'input_tax_credit_total' => (float) $this->input_tax_credit_total,
            'net_tax_payable' => (float) $this->net_tax_payable,
            'summary' => $this->summary ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'approved_at' => $this->approved_at?->toISOString(),
            'locked_at' => $this->locked_at?->toISOString(),
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
            'locked_by' => $this->whenLoaded('lockedBy', fn (): ?array => $this->lockedBy ? [
                'id' => $this->lockedBy->id,
                'name' => $this->lockedBy->name,
                'email' => $this->lockedBy->email,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
