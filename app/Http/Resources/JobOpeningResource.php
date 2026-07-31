<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobOpeningResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'opening_code' => $this->opening_code,
            'title' => $this->title,
            'department' => $this->department,
            'designation' => $this->designation,
            'positions' => $this->positions,
            'employment_type' => $this->employment_type,
            'work_location' => $this->work_location,
            'budget_min_ctc' => (float) $this->budget_min_ctc,
            'budget_max_ctc' => (float) $this->budget_max_ctc,
            'status' => $this->status,
            'target_hiring_date' => $this->target_hiring_date?->toDateString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'required_skills' => $this->required_skills ?? [],
            'workflow_history' => $this->metadata['workflow_history'] ?? [],
            'business_justification' => $this->metadata['business_justification'] ?? null,
            'company' => $this->whenLoaded('company', fn (): array => [
                'id' => $this->company->id,
                'name' => $this->company->name,
            ]),
            'branch' => $this->whenLoaded('branch', fn (): ?array => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ] : null),
            'project' => $this->whenLoaded('project', fn (): ?array => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ] : null),
            'created_by' => $this->whenLoaded('createdBy', fn (): ?array => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'email' => $this->createdBy->email,
            ] : null),
            'reviewed_by' => $this->whenLoaded('reviewedBy', fn (): ?array => $this->reviewedBy ? [
                'id' => $this->reviewedBy->id,
                'name' => $this->reviewedBy->name,
                'email' => $this->reviewedBy->email,
            ] : null),
        ];
    }
}
