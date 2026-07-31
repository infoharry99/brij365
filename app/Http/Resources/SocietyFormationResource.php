<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SocietyFormationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'formation_number' => $this->formation_number,
            'society_name' => $this->society_name,
            'association_type' => $this->association_type,
            'total_units' => (int) $this->total_units,
            'occupied_units' => (int) $this->occupied_units,
            'registration_number' => $this->registration_number,
            'application_filed_on' => $this->application_filed_on?->toDateString(),
            'registered_on' => $this->registered_on?->toDateString(),
            'target_handover_on' => $this->target_handover_on?->toDateString(),
            'status' => $this->status,
            'progress_percent' => (int) $this->progress_percent,
            'current_stage' => $this->current_stage,
            'next_step' => $this->next_step,
            'committee_members' => $this->committee_members ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'project' => $this->whenLoaded('project', fn (): array => [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn (): ?array => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ] : null),
        ];
    }
}
