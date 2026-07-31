<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PerformanceReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();
        $canViewScoreTrace = $actor?->can('viewScoreTrace', $this->resource) === true;
        $canViewOverrideGovernance = $actor?->can('viewOverrideGovernance', $this->resource) === true;

        return [
            'id' => $this->id,
            'review_number' => $this->review_number,
            'status' => $this->status,
            'lock_version' => (int) $this->lock_version,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'self_submitted_at' => $this->self_submitted_at?->toISOString(),
            'manager_submitted_at' => $this->manager_submitted_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'kpis' => $this->kpis ?? [],
            'kra_summary' => $this->kra_summary ?? [],
            'self_score' => $this->self_score === null ? null : (float) $this->self_score,
            'manager_score' => $this->manager_score === null ? null : (float) $this->manager_score,
            'final_score' => $this->final_score === null ? null : (float) $this->final_score,
            'final_rating' => $this->final_rating,
            'strengths' => $this->strengths,
            'improvement_areas' => $this->improvement_areas,
            'manager_comments' => $this->manager_comments,
            'hr_comments' => $this->hr_comments,
            'pip_required' => $this->pip_required,
            'pip_status' => $this->pip_status,
            'pip_plan' => $this->pip_plan ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'score_snapshot' => $this->when(
                $canViewScoreTrace && $this->relationLoaded('scoreSnapshot'),
                fn (): ?array => $this->scoreSnapshot ? [
                    'id' => $this->scoreSnapshot->id,
                    'normalized_score' => (float) $this->scoreSnapshot->total_score,
                    'score_band' => $this->scoreSnapshot->score_band,
                    'is_override' => (bool) $this->scoreSnapshot->is_override,
                    'rule_version' => (int) $this->scoreSnapshot->rule_version,
                    'rule_checksum' => $this->scoreSnapshot->scoringRule?->configuration_checksum,
                    'component_scores' => $this->scoreSnapshot->component_scores ?? [],
                    'applied_weights' => $this->scoreSnapshot->applied_weights ?? [],
                    'input_hash' => $this->scoreSnapshot->input_hash,
                    'calculated_at' => $this->scoreSnapshot->calculated_at?->toISOString(),
                ] : null,
            ),
            'score_override_requests' => $this->when(
                $canViewOverrideGovernance && $this->relationLoaded('scoreOverrideRequests'),
                fn (): array => $this->scoreOverrideRequests->map(static fn ($override): array => [
                    'id' => $override->id,
                    'score_snapshot_id' => $override->score_snapshot_id,
                    'requested_score' => (float) $override->requested_score,
                    'status' => $override->status,
                    'reason' => $override->reason,
                    'evidence' => $override->evidence,
                    'requested_by' => $override->requestedBy?->name,
                    'decided_by' => $override->decidedBy?->name,
                    'decision_reason' => $override->decision_reason,
                    'decided_at' => $override->decided_at?->toISOString(),
                    'created_at' => $override->created_at?->toISOString(),
                ])->values()->all(),
            ),
            'cycle' => $this->whenLoaded('cycle', fn (): ?array => $this->cycle ? [
                'id' => $this->cycle->id,
                'cycle_code' => $this->cycle->cycle_code,
                'name' => $this->cycle->name,
                'frequency' => $this->cycle->frequency,
                'status' => $this->cycle->status,
            ] : null),
            'employee' => $this->whenLoaded('employee', fn (): ?array => $this->employee ? [
                'id' => $this->employee->id,
                'employee_code' => $this->employee->employee_code,
                'name' => $this->employee->name,
                'designation' => $this->employee->designation,
                'department' => $this->employee->department,
            ] : null),
            'manager' => $this->whenLoaded('managerEmployee', fn (): ?array => $this->managerEmployee ? [
                'id' => $this->managerEmployee->id,
                'employee_code' => $this->managerEmployee->employee_code,
                'name' => $this->managerEmployee->name,
            ] : null),
            'self_reviewer' => $this->whenLoaded('selfReviewer', fn (): ?array => $this->selfReviewer ? [
                'id' => $this->selfReviewer->id,
                'name' => $this->selfReviewer->name,
            ] : null),
            'manager_reviewer' => $this->whenLoaded('managerReviewer', fn (): ?array => $this->managerReviewer ? [
                'id' => $this->managerReviewer->id,
                'name' => $this->managerReviewer->name,
            ] : null),
            'hr_reviewer' => $this->whenLoaded('hrReviewer', fn (): ?array => $this->hrReviewer ? [
                'id' => $this->hrReviewer->id,
                'name' => $this->hrReviewer->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
