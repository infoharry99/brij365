<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectApprovalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'approval_code' => $this->approval_code,
            'approval_type' => $this->approval_type,
            'authority_name' => $this->authority_name,
            'application_number' => $this->application_number,
            'applied_on' => $this->applied_on?->toDateString(),
            'approved_on' => $this->approved_on?->toDateString(),
            'expires_on' => $this->expires_on?->toDateString(),
            'status' => $this->status,
            'required_for' => $this->required_for,
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
            'responsible_user' => $this->whenLoaded('responsibleUser', fn (): ?array => $this->responsibleUser ? [
                'id' => $this->responsibleUser->id,
                'name' => $this->responsibleUser->name,
                'email' => $this->responsibleUser->email,
            ] : null),
            'verified_by' => $this->whenLoaded('verifiedBy', fn (): ?array => $this->verifiedBy ? [
                'id' => $this->verifiedBy->id,
                'name' => $this->verifiedBy->name,
                'email' => $this->verifiedBy->email,
            ] : null),
        ];
    }
}
