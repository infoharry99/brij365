<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteVisitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'visit_number' => $this->visit_number,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'duration_minutes' => $this->duration_minutes,
            'visit_mode' => $this->visit_mode,
            'meeting_location' => $this->meeting_location,
            'meeting_url' => $this->meeting_url,
            'agenda' => $this->agenda,
            'outcome' => $this->outcome,
            'outcome_notes' => $this->outcome_notes,
            'completed_at' => $this->completed_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'next_follow_up_at' => $this->next_follow_up_at?->toISOString(),
            'attendees' => $this->attendees ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'metadata' => $this->metadata ?? [],
            'lead' => $this->whenLoaded('lead', fn (): array => [
                'id' => $this->lead->id,
                'lead_code' => $this->lead->lead_code,
                'stage' => $this->lead->stage,
            ]),
            'customer' => $this->whenLoaded('customer', fn (): array => [
                'id' => $this->customer->id,
                'code' => $this->customer->code,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
            ]),
            'project' => $this->whenLoaded('project', fn (): ?array => $this->project ? [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ] : null),
            'scheduled_by' => $this->whenLoaded('scheduledBy', fn (): array => [
                'id' => $this->scheduledBy->id,
                'name' => $this->scheduledBy->name,
                'email' => $this->scheduledBy->email,
            ]),
            'assigned_to' => $this->whenLoaded('assignedTo', fn (): ?array => $this->assignedTo ? [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
                'email' => $this->assignedTo->email,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
