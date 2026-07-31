<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryStructureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'version' => $this->version,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'monthly_ctc' => (float) $this->monthly_ctc,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'components' => $this->whenLoaded('components', fn () => $this->components->map(fn ($component) => [
                'component_code' => $component->payrollComponent?->code,
                'component_name' => $component->payrollComponent?->name,
                'component_type' => $component->payrollComponent?->component_type,
                'amount' => (float) $component->amount,
                'percentage_of_ctc' => (float) $component->percentage_of_ctc,
            ])->values()),
        ];
    }
}
