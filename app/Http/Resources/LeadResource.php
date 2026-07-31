<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lead_code' => $this->lead_code,
            'source' => $this->source,
            'stage' => $this->stage,
            'status' => $this->status,
            'budget_min' => $this->budget_min !== null ? (float) $this->budget_min : null,
            'budget_max' => $this->budget_max !== null ? (float) $this->budget_max : null,
            'expected_value' => (float) $this->expected_value,
            'follow_up_at' => $this->follow_up_at?->toISOString(),
            'disposition' => [
                'outcome' => $this->disposition_outcome,
                'reason' => $this->disposition_reason,
                'competitor_name' => $this->competitor_name,
                'note' => $this->disposition_note,
                'by' => $this->whenLoaded('dispositionedBy', fn () => $this->dispositionedBy ? [
                    'name' => $this->dispositionedBy->name,
                    'email' => $this->dispositionedBy->email,
                ] : null),
                'at' => $this->dispositioned_at?->toISOString(),
            ],
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'code' => $this->company->code,
                'name' => $this->company->name,
            ]),
            'project' => $this->whenLoaded('project', fn () => $this->project ? [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
                'city' => $this->project->city,
            ] : null),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'code' => $this->customer->code,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
            ]),
            'partner' => $this->whenLoaded('partner', fn () => $this->partner ? [
                'id' => $this->partner->id,
                'code' => $this->partner->code,
                'name' => $this->partner->name,
                'type' => $this->partner->partner_type,
            ] : null),
            'marketing_campaign' => $this->whenLoaded('marketingCampaign', fn () => $this->marketingCampaign ? [
                'campaign_code' => $this->marketingCampaign->campaign_code,
                'name' => $this->marketingCampaign->name,
                'channel' => $this->marketingCampaign->channel,
                'source' => $this->marketingCampaign->source,
                'status' => $this->marketingCampaign->status,
            ] : null),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? [
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
