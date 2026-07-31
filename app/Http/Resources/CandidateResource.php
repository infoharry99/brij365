<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CandidateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $offer = $this->relationLoaded('offer') ? $this->offer : null;

        return [
            'id' => $this->id,
            'candidate_code' => $this->candidate_code,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'source' => $this->source,
            'current_company' => $this->current_company,
            'experience_years' => (float) $this->experience_years,
            'current_ctc' => $this->current_ctc === null ? null : (float) $this->current_ctc,
            'expected_ctc' => $this->expected_ctc === null ? null : (float) $this->expected_ctc,
            'notice_period_days' => $this->notice_period_days,
            'skills' => $this->skills ?? [],
            'documents' => $this->documents ?? [],
            'stage' => $this->stage,
            'status' => $this->status,
            'stage_history' => $this->stage_history ?? [],
            'notes' => $this->notes,
            'job_opening' => $this->whenLoaded('jobOpening', fn (): array => [
                'id' => $this->jobOpening->id,
                'opening_code' => $this->jobOpening->opening_code,
                'title' => $this->jobOpening->title,
                'department' => $this->jobOpening->department,
            ]),
            'owner' => $this->whenLoaded('owner', fn (): ?array => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ] : null),
            'employee' => $this->whenLoaded('employee', fn (): ?array => $this->employee ? [
                'id' => $this->employee->id,
                'employee_code' => $this->employee->employee_code,
                'name' => $this->employee->name,
                'designation' => $this->employee->designation,
                'department' => $this->employee->department,
                'status' => $this->employee->status,
                'joined_on' => $this->employee->joined_on?->toDateString(),
            ] : null),
            'interviews' => InterviewResource::collection($this->whenLoaded('interviews')),
            'offer' => new JobOfferResource($this->whenLoaded('offer')),
            'permissions' => [
                'update' => $request->user()?->can('update', $this->resource) === true,
                'convert' => $request->user()?->can('convert', $this->resource) === true,
            ],
            'can_transition_stage' => $request->user()?->can('update', $this->resource) === true
                && $this->status === 'active'
                && $this->employee_id === null
                && in_array($this->stage, ['screening', 'interviewed'], true),
            'can_convert_to_employee' => $request->user()?->can('convert', $this->resource) === true
                && $this->status === 'active'
                && $this->employee_id === null
                && $offer?->status === 'released',
        ];
    }
}
