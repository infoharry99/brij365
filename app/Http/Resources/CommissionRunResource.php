<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommissionRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'run_number' => $this->run_number,
            'period_year' => $this->period_year,
            'period_month' => $this->period_month,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'status' => $this->status,
            'item_count' => $this->item_count,
            'source_total' => (float) $this->source_total,
            'eligible_total' => (float) $this->eligible_total,
            'commission_total' => (float) $this->commission_total,
            'calculation_summary' => $this->calculation_summary,
            'workflow_history' => $this->workflow_history,
            'approved_at' => $this->approved_at?->toISOString(),
            'rule' => $this->whenLoaded('rule', fn () => $this->rule ? [
                'id' => $this->rule->id,
                'rule_code' => $this->rule->rule_code,
                'name' => $this->rule->name,
                'rule_type' => $this->rule->rule_type,
                'basis' => $this->rule->basis,
            ] : null),
            'generated_by' => $this->whenLoaded('generatedBy', fn () => $this->generatedBy ? [
                'name' => $this->generatedBy->name,
                'email' => $this->generatedBy->email,
            ] : null),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy ? [
                'name' => $this->approvedBy->name,
                'email' => $this->approvedBy->email,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'employee_code' => $item->employee?->employee_code,
                'employee_name' => $item->employee?->name,
                'booking_code' => $item->booking?->booking_code,
                'lead_code' => $item->lead?->lead_code,
                'partner_name' => $item->partner?->name,
                'source_amount' => (float) $item->source_amount,
                'eligible_amount' => (float) $item->eligible_amount,
                'commission_amount' => (float) $item->commission_amount,
                'status' => $item->status,
                'payroll_run_item_id' => $item->payroll_run_item_id,
                'payroll_included_at' => $item->payroll_included_at?->toISOString(),
                'metadata' => $item->metadata,
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
