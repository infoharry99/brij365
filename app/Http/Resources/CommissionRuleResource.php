<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommissionRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rule_code' => $this->rule_code,
            'name' => $this->name,
            'rule_type' => $this->rule_type,
            'basis' => $this->basis,
            'rate_percent' => (float) $this->rate_percent,
            'fixed_amount' => (float) $this->fixed_amount,
            'target_amount' => (float) $this->target_amount,
            'slab_rules' => $this->slab_rules,
            'eligibility_rules' => $this->eligibility_rules,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'status' => $this->status,
            'project' => $this->whenLoaded('project', fn () => $this->project ? [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ] : null),
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy ? [
                'name' => $this->createdBy->name,
                'email' => $this->createdBy->email,
            ] : null),
            'workflow_history' => $this->workflow_history,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
