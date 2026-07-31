<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommonAreaHandoverItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_number' => $this->item_number,
            'facility_name' => $this->facility_name,
            'category' => $this->category,
            'checklist_total' => (int) $this->checklist_total,
            'checklist_completed' => (int) $this->checklist_completed,
            'completion_percent' => $this->checklist_total > 0 ? round($this->checklist_completed / $this->checklist_total * 100) : 0,
            'status' => $this->status,
            'target_completion_on' => $this->target_completion_on?->toDateString(),
            'signed_off_on' => $this->signed_off_on?->toDateString(),
            'snag_summary' => $this->snag_summary ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'project' => $this->whenLoaded('project', fn (): array => [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ]),
            'society_formation' => $this->whenLoaded('societyFormation', fn (): ?array => $this->societyFormation ? [
                'id' => $this->societyFormation->id,
                'formation_number' => $this->societyFormation->formation_number,
                'society_name' => $this->societyFormation->society_name,
            ] : null),
            'responsible_user' => $this->whenLoaded('responsibleUser', fn (): ?array => $this->responsibleUser ? [
                'id' => $this->responsibleUser->id,
                'name' => $this->responsibleUser->name,
            ] : null),
            'signed_off_by' => $this->whenLoaded('signedOffBy', fn (): ?array => $this->signedOffBy ? [
                'id' => $this->signedOffBy->id,
                'name' => $this->signedOffBy->name,
            ] : null),
        ];
    }
}
