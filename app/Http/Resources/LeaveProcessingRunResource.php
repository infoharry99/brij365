<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveProcessingRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'run_number' => $this->run_number,
            'period_year' => $this->period_year,
            'processing_type' => $this->processing_type,
            'status' => $this->status,
            'is_dry_run' => $this->is_dry_run,
            'rules_snapshot' => $this->rules_snapshot ?? [],
            'summary' => $this->summary ?? [],
            'line_items' => $this->line_items ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'posted_at' => $this->posted_at?->toISOString(),
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->userSummary($this->createdBy)),
            'posted_by' => $this->whenLoaded('postedBy', fn () => $this->userSummary($this->postedBy)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function userSummary($user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name, 'email' => $user->email] : null;
    }
}
