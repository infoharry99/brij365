<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProspectInquiryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inquiry_number' => $this->inquiry_number,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'source' => $this->source,
            'channel' => $this->channel,
            'preferred_contact_method' => $this->preferred_contact_method,
            'status' => $this->status,
            'budget_min' => $this->budget_min !== null ? (float) $this->budget_min : null,
            'budget_max' => $this->budget_max !== null ? (float) $this->budget_max : null,
            'message' => $this->message,
            'consent_to_contact' => (bool) $this->consent_to_contact,
            'utm_source' => $this->utm_source,
            'utm_medium' => $this->utm_medium,
            'utm_campaign' => $this->utm_campaign,
            'metadata' => $this->metadata ?? [],
            'assigned_at' => $this->assigned_at?->toISOString(),
            'converted_at' => $this->converted_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'company' => $this->whenLoaded('company', fn () => [
                'code' => $this->company->code,
                'name' => $this->company->name,
            ]),
            'project' => $this->whenLoaded('project', fn () => [
                'code' => $this->project->code,
                'name' => $this->project->name,
                'city' => $this->project->city,
            ]),
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo ? [
                'name' => $this->assignedTo->name,
                'email' => $this->assignedTo->email,
            ] : null),
            'converted_lead' => $this->whenLoaded('convertedLead', fn () => $this->convertedLead ? [
                'lead_code' => $this->convertedLead->lead_code,
                'stage' => $this->convertedLead->stage,
                'status' => $this->convertedLead->status,
            ] : null),
            'duplicate_of' => $this->whenLoaded('duplicateOf', fn () => $this->duplicateOf ? [
                'inquiry_number' => $this->duplicateOf->inquiry_number,
                'status' => $this->duplicateOf->status,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
