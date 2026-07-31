<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollRunResource extends JsonResource
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
            'working_days' => $this->working_days,
            'status' => $this->status,
            'gross_earnings' => (float) $this->gross_earnings,
            'total_deductions' => (float) $this->total_deductions,
            'net_payable' => (float) $this->net_payable,
            'metadata' => $this->metadata,
            'approved_at' => $this->approved_at?->toISOString(),
            'generated_by' => $this->whenLoaded('generatedBy', fn () => $this->generatedBy ? [
                'name' => $this->generatedBy->name,
                'email' => $this->generatedBy->email,
            ] : null),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy ? [
                'name' => $this->approvedBy->name,
                'email' => $this->approvedBy->email,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'employee_code' => $item->employee?->employee_code,
                'employee_name' => $item->employee?->name,
                'monthly_ctc' => (float) $item->monthly_ctc,
                'payable_days' => $item->payable_days,
                'gross_earnings' => (float) $item->gross_earnings,
                'total_deductions' => (float) $item->total_deductions,
                'net_payable' => (float) $item->net_payable,
                'component_breakup' => $item->component_breakup,
                'status' => $item->status,
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
