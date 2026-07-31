<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadQualificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'qualification_number' => $this->qualification_number,
            'status' => $this->status,
            'score' => $this->score,
            'budget_score' => $this->budget_score,
            'authority_score' => $this->authority_score,
            'need_score' => $this->need_score,
            'timeline_score' => $this->timeline_score,
            'preferred_configuration' => $this->preferred_configuration,
            'verified_budget_min' => $this->verified_budget_min !== null ? (float) $this->verified_budget_min : null,
            'verified_budget_max' => $this->verified_budget_max !== null ? (float) $this->verified_budget_max : null,
            'expected_booking_date' => $this->expected_booking_date?->toDateString(),
            'decision_notes' => $this->decision_notes,
            'requirements' => $this->requirements ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'metadata' => $this->metadata ?? [],
            'quality_score' => is_array($this->metadata) ? ($this->metadata['quality_score'] ?? null) : null,
            'qualified_at' => $this->qualified_at?->toISOString(),
            'lead' => $this->whenLoaded('lead', fn (): array => [
                'id' => $this->lead->id,
                'lead_code' => $this->lead->lead_code,
                'stage' => $this->lead->stage,
                'status' => $this->lead->status,
            ]),
            'qualified_by' => $this->whenLoaded('qualifiedBy', fn (): array => [
                'id' => $this->qualifiedBy->id,
                'name' => $this->qualifiedBy->name,
                'email' => $this->qualifiedBy->email,
            ]),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
