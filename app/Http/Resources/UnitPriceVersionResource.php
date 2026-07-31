<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitPriceVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'price_code' => $this->price_code,
            'version_number' => $this->version_number,
            'status' => $this->status,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'base_rate' => (float) $this->base_rate,
            'base_price' => (float) $this->base_price,
            'floor_premium' => (float) $this->floor_premium,
            'location_premium' => (float) $this->location_premium,
            'parking_charges' => (float) $this->parking_charges,
            'other_charges' => (float) $this->other_charges,
            'tax_rate_percent' => (float) $this->tax_rate_percent,
            'gross_price_before_tax' => (float) $this->gross_price_before_tax,
            'tax_amount' => (float) $this->tax_amount,
            'total_price' => (float) $this->total_price,
            'charge_breakup' => $this->charge_breakup ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'metadata' => $this->metadata ?? [],
            'approved_at' => $this->approved_at?->toISOString(),
            'company' => $this->whenLoaded('company', fn () => [
                'code' => $this->company->code,
                'name' => $this->company->name,
            ]),
            'project' => $this->whenLoaded('project', fn () => [
                'code' => $this->project->code,
                'name' => $this->project->name,
            ]),
            'unit' => $this->whenLoaded('unit', fn () => [
                'unit_code' => $this->unit->unit_code,
                'unit_type' => $this->unit->unit_type,
                'saleable_area_sqft' => (float) $this->unit->saleable_area_sqft,
                'status' => $this->unit->status,
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'name' => $this->createdBy->name,
                'email' => $this->createdBy->email,
            ]),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy ? [
                'name' => $this->approvedBy->name,
                'email' => $this->approvedBy->email,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
