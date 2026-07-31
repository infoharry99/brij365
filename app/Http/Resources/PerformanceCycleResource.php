<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PerformanceCycleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cycle_code' => $this->cycle_code,
            'name' => $this->name,
            'frequency' => $this->frequency,
            'status' => $this->status,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'review_due_on' => $this->review_due_on?->toDateString(),
            'department' => $this->department,
            'rating_scale_min' => $this->rating_scale_min,
            'rating_scale_max' => $this->rating_scale_max,
            'passing_score' => (float) $this->passing_score,
            'rules' => $this->rules ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'activated_at' => $this->activated_at?->toISOString(),
            'company' => $this->whenLoaded('company', fn (): ?array => $this->company ? [
                'id' => $this->company->id,
                'code' => $this->company->code,
                'name' => $this->company->name,
            ] : null),
            'project' => $this->whenLoaded('project', fn (): ?array => $this->project ? [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ] : null),
            'created_by' => $this->whenLoaded('createdBy', fn (): ?array => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ] : null),
            'activated_by' => $this->whenLoaded('activatedBy', fn (): ?array => $this->activatedBy ? [
                'id' => $this->activatedBy->id,
                'name' => $this->activatedBy->name,
            ] : null),
            'reviews_count' => $this->whenCounted('reviews'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
