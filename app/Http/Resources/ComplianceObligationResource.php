<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplianceObligationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'obligation_number' => $this->obligation_number,
            'title' => $this->title,
            'compliance_type' => $this->compliance_type,
            'due_on' => $this->due_on?->toDateString(),
            'frequency' => $this->frequency,
            'priority' => $this->priority,
            'status' => $this->status,
            'evidence_document_reference' => $this->evidence_document_reference,
            'notes' => $this->notes,
            'workflow_history' => $this->workflow_history ?? [],
            'metadata' => $this->metadata ?? [],
            'completed_at' => $this->completed_at?->toISOString(),
            'project' => $this->whenLoaded('project', fn (): ?array => $this->project ? [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ] : null),
            'assigned_to' => $this->whenLoaded('assignedTo', fn (): ?array => $this->assignedTo ? [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
                'email' => $this->assignedTo->email,
            ] : null),
            'completed_by' => $this->whenLoaded('completedBy', fn (): ?array => $this->completedBy ? [
                'id' => $this->completedBy->id,
                'name' => $this->completedBy->name,
                'email' => $this->completedBy->email,
            ] : null),
        ];
    }
}
