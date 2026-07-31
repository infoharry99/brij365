<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendarEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_number' => $this->event_number,
            'title' => $this->title,
            'description' => $this->description,
            'event_type' => $this->event_type,
            'status' => $this->status,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'timezone' => $this->timezone,
            'location' => $this->location,
            'meeting_url' => $this->meeting_url,
            'visibility' => $this->visibility,
            'attendees' => $this->attendees ?? [],
            'reminders' => $this->reminders ?? [],
            'related_type' => $this->related_type,
            'related_id' => $this->related_id,
            'workflow_history' => $this->workflow_history ?? [],
            'metadata' => $this->metadata ?? [],
            'company' => $this->whenLoaded('company', fn (): array => [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'code' => $this->company->code,
            ]),
            'project' => $this->whenLoaded('project', fn (): ?array => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
                'code' => $this->project->code,
            ] : null),
            'organizer' => $this->whenLoaded('organizer', fn (): array => [
                'id' => $this->organizer->id,
                'name' => $this->organizer->name,
                'email' => $this->organizer->email,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
