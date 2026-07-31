<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReraRegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'registration_number' => $this->registration_number,
            'authority_name' => $this->authority_name,
            'state_code' => $this->state_code,
            'registered_on' => $this->registered_on?->toDateString(),
            'expires_on' => $this->expires_on?->toDateString(),
            'status' => $this->status,
            'document_reference' => $this->document_reference,
            'conditions' => $this->conditions ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'metadata' => $this->metadata ?? [],
            'verified_at' => $this->verified_at?->toISOString(),
            'project' => $this->whenLoaded('project', fn (): array => [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn (): ?array => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'email' => $this->createdBy->email,
            ] : null),
            'verified_by' => $this->whenLoaded('verifiedBy', fn (): ?array => $this->verifiedBy ? [
                'id' => $this->verifiedBy->id,
                'name' => $this->verifiedBy->name,
                'email' => $this->verifiedBy->email,
            ] : null),
        ];
    }
}
