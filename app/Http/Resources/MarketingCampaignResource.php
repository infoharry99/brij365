<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketingCampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'campaign_code' => $this->campaign_code,
            'name' => $this->name,
            'channel' => $this->channel,
            'source' => $this->source,
            'status' => $this->status,
            'start_on' => $this->start_on?->toDateString(),
            'end_on' => $this->end_on?->toDateString(),
            'budget_amount' => (float) $this->budget_amount,
            'target_leads' => $this->target_leads,
            'target_bookings' => $this->target_bookings,
            'utm_source' => $this->utm_source,
            'utm_medium' => $this->utm_medium,
            'utm_campaign' => $this->utm_campaign,
            'audience_segment' => $this->audience_segment,
            'approved_at' => $this->approved_at?->toISOString(),
            'company' => $this->whenLoaded('company', fn () => [
                'code' => $this->company->code,
                'name' => $this->company->name,
            ]),
            'project' => $this->whenLoaded('project', fn () => $this->project ? [
                'code' => $this->project->code,
                'name' => $this->project->name,
                'city' => $this->project->city,
            ] : null),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'name' => $this->createdBy->name,
                'email' => $this->createdBy->email,
            ]),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy ? [
                'name' => $this->approvedBy->name,
                'email' => $this->approvedBy->email,
            ] : null),
            'metrics' => $this->when(isset($this->metrics), $this->metrics ?? null),
            'workflow_history' => $this->workflow_history ?? [],
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
