<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'offer_number' => $this->offer_number,
            'template_code' => $this->template_code,
            'offered_ctc' => (float) $this->offered_ctc,
            'joining_date' => $this->joining_date?->toDateString(),
            'placeholders' => $this->placeholders ?? [],
            'status' => $this->status,
            'released_at' => $this->released_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'document_history' => $this->document_history ?? [],
            'candidate' => $this->whenLoaded('candidate', fn (): array => [
                'id' => $this->candidate->id,
                'candidate_code' => $this->candidate->candidate_code,
                'name' => $this->candidate->name,
                'stage' => $this->candidate->stage,
                'job_opening' => $this->candidate->relationLoaded('jobOpening') && $this->candidate->jobOpening ? [
                    'id' => $this->candidate->jobOpening->id,
                    'title' => $this->candidate->jobOpening->title,
                    'department' => $this->candidate->jobOpening->department,
                ] : null,
            ]),
            'created_by' => $this->whenLoaded('createdBy', fn (): ?array => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'email' => $this->createdBy->email,
            ] : null),
            'released_by' => $this->whenLoaded('releasedBy', fn (): ?array => $this->releasedBy ? [
                'id' => $this->releasedBy->id,
                'name' => $this->releasedBy->name,
                'email' => $this->releasedBy->email,
            ] : null),
            'accepted_by' => $this->whenLoaded('acceptedBy', fn (): ?array => $this->acceptedBy ? [
                'id' => $this->acceptedBy->id,
                'name' => $this->acceptedBy->name,
                'email' => $this->acceptedBy->email,
            ] : null),
            'permissions' => [
                'release' => $request->user()?->can('release', $this->resource) === true,
            ],
        ];
    }
}
