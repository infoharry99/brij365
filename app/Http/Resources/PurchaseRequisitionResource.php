<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseRequisitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requisition_number' => $this->requisition_number,
            'department' => $this->department,
            'required_by' => $this->required_by?->toDateString(),
            'priority' => $this->priority,
            'status' => $this->status,
            'items' => $this->items ?? [],
            'estimated_total' => (float) $this->estimated_total,
            'purpose' => $this->purpose,
            'workflow_history' => $this->workflow_history ?? [],
            'approved_at' => $this->approved_at?->toISOString(),
            'project' => $this->whenLoaded('project', fn (): array => [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ]),
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
        ];
    }
}
