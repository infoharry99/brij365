<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialVoucherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'voucher_number' => $this->voucher_number,
            'voucher_type' => $this->voucher_type,
            'status' => $this->status,
            'voucher_date' => $this->voucher_date?->toDateString(),
            'reference_number' => $this->reference_number,
            'narration' => $this->narration,
            'currency' => $this->currency,
            'total_debit' => (float) $this->total_debit,
            'total_credit' => (float) $this->total_credit,
            'tax_summary' => $this->tax_summary ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'metadata' => $this->metadata ?? [],
            'approved_at' => $this->approved_at?->toISOString(),
            'rejected_at' => $this->rejected_at?->toISOString(),
            'company' => $this->whenLoaded('company', fn (): array => [
                'id' => $this->company->id,
                'code' => $this->company->code,
                'name' => $this->company->name,
            ]),
            'project' => $this->whenLoaded('project', fn (): ?array => $this->project ? [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ] : null),
            'created_by' => $this->whenLoaded('createdBy', fn (): array => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'email' => $this->createdBy->email,
            ]),
            'approved_by' => $this->whenLoaded('approvedBy', fn (): ?array => $this->approvedBy ? [
                'id' => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
                'email' => $this->approvedBy->email,
            ] : null),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line): array => [
                'id' => $line->id,
                'line_number' => $line->line_number,
                'account_code' => $line->account_code,
                'account_name' => $line->account_name,
                'line_type' => $line->line_type,
                'amount' => (float) $line->amount,
                'party_type' => $line->party_type,
                'party_id' => $line->party_id,
                'cost_center' => $line->cost_center,
                'tax_rate' => (float) $line->tax_rate,
                'tax_amount' => (float) $line->tax_amount,
                'description' => $line->description,
                'metadata' => $line->metadata ?? [],
                'project' => $line->relationLoaded('project') && $line->project ? [
                    'id' => $line->project->id,
                    'code' => $line->project->code,
                    'name' => $line->project->name,
                ] : null,
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
