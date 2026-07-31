<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $panelUsers = $this->relationLoaded('panelUsers')
            ? $this->getRelation('panelUsers')
            : collect();

        return [
            'id' => $this->id,
            'interview_code' => $this->interview_code,
            'round_name' => $this->round_name,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'duration_minutes' => $this->duration_minutes,
            'mode' => $this->mode,
            'venue_or_link' => $this->venue_or_link,
            'status' => $this->status,
            'feedback' => $this->feedback ?? [],
            'panel' => $panelUsers
                ->map(fn ($user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->values(),
            'candidate' => $this->whenLoaded('candidate', fn (): array => [
                'id' => $this->candidate->id,
                'candidate_code' => $this->candidate->candidate_code,
                'name' => $this->candidate->name,
                'stage' => $this->candidate->stage,
            ]),
            'scheduled_by' => $this->whenLoaded('scheduledBy', fn (): ?array => $this->scheduledBy ? [
                'id' => $this->scheduledBy->id,
                'name' => $this->scheduledBy->name,
                'email' => $this->scheduledBy->email,
            ] : null),
        ];
    }
}
