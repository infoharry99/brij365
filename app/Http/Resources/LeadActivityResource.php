<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'activity_number' => $this->activity_number,
            'activity_type' => $this->activity_type,
            'activity_at' => $this->activity_at?->toISOString(),
            'subject' => $this->subject,
            'description' => $this->description,
            'old_stage' => $this->old_stage,
            'new_stage' => $this->new_stage,
            'outcome' => $this->outcome,
            'next_follow_up_at' => $this->next_follow_up_at?->toISOString(),
            'lead' => $this->whenLoaded('lead', fn () => [
                'lead_code' => $this->lead->lead_code,
                'stage' => $this->lead->stage,
                'status' => $this->lead->status,
            ]),
            'customer' => $this->whenLoaded('lead.customer', fn () => [
                'code' => $this->lead->customer->code,
                'name' => $this->lead->customer->name,
            ]),
            'project' => $this->whenLoaded('project', fn () => $this->project ? [
                'code' => $this->project->code,
                'name' => $this->project->name,
            ] : null),
            'actor' => $this->whenLoaded('actor', fn () => $this->actor ? [
                'name' => $this->actor->name,
                'email' => $this->actor->email,
            ] : null),
            'marketing_campaign' => $this->whenLoaded('marketingCampaign', fn () => $this->marketingCampaign ? [
                'campaign_code' => $this->marketingCampaign->campaign_code,
                'name' => $this->marketingCampaign->name,
                'channel' => $this->marketingCampaign->channel,
            ] : null),
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
