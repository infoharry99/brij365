<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoqItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'boq_code' => $this->boq_code,
            'trade' => $this->trade,
            'description' => $this->description,
            'unit' => $this->unit,
            'planned_quantity' => (float) $this->planned_quantity,
            'rate' => (float) $this->rate,
            'budget_amount' => (float) $this->budget_amount,
            'measured_quantity' => (float) $this->measured_quantity,
            'certified_quantity' => (float) $this->certified_quantity,
            'certified_amount' => (float) $this->certified_amount,
            'balance_quantity' => round((float) $this->planned_quantity - (float) $this->certified_quantity, 3),
            'status' => $this->status,
            'specifications' => $this->specifications ?? [],
            'metadata' => $this->metadata ?? [],
            'project' => $this->whenLoaded('project', fn (): array => [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ]),
            'milestone' => $this->whenLoaded('milestone', fn (): ?array => $this->milestone ? [
                'id' => $this->milestone->id,
                'milestone_code' => $this->milestone->milestone_code,
                'name' => $this->milestone->name,
            ] : null),
            'vendor' => $this->whenLoaded('vendor', fn (): ?array => $this->vendor ? [
                'id' => $this->vendor->id,
                'vendor_code' => $this->vendor->vendor_code,
                'name' => $this->vendor->name,
            ] : null),
            'created_by' => $this->whenLoaded('createdBy', fn (): ?array => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'email' => $this->createdBy->email,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
