<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConstructionMilestoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'milestone_code' => $this->milestone_code,
            'name' => $this->name,
            'phase' => $this->phase,
            'planned_start_on' => $this->planned_start_on?->toDateString(),
            'planned_end_on' => $this->planned_end_on?->toDateString(),
            'actual_start_on' => $this->actual_start_on?->toDateString(),
            'actual_end_on' => $this->actual_end_on?->toDateString(),
            'weight_percent' => (float) $this->weight_percent,
            'progress_percent' => (float) $this->progress_percent,
            'status' => $this->status,
            'dependencies' => $this->dependencies ?? [],
            'metadata' => $this->metadata ?? [],
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
        ];
    }
}
