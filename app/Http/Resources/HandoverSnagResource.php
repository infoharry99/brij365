<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HandoverSnagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'snag_number' => $this->snag_number,
            'area' => $this->area,
            'category' => $this->category,
            'severity' => $this->severity,
            'description' => $this->description,
            'status' => $this->status,
            'target_resolution_on' => $this->target_resolution_on?->toDateString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'resolution_notes' => $this->resolution_notes,
            'attachments' => $this->attachments ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'handover' => $this->whenLoaded('handover', fn (): array => [
                'id' => $this->handover->id,
                'handover_number' => $this->handover->handover_number,
                'status' => $this->handover->status,
            ]),
            'reported_by' => $this->whenLoaded('reportedBy', fn (): ?array => $this->reportedBy ? [
                'id' => $this->reportedBy->id,
                'name' => $this->reportedBy->name,
                'email' => $this->reportedBy->email,
            ] : null),
            'resolved_by' => $this->whenLoaded('resolvedBy', fn (): ?array => $this->resolvedBy ? [
                'id' => $this->resolvedBy->id,
                'name' => $this->resolvedBy->name,
                'email' => $this->resolvedBy->email,
            ] : null),
        ];
    }
}
