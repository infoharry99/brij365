<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeExitInterviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canViewConfidential = $request->user()?->can('viewConfidential', $this->resource) === true;

        return [
            'id' => $this->id,
            'interview_number' => $this->interview_number,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'separation_settlement_id' => $this->employee_separation_settlement_id,
            'separation_settlement' => new EmployeeSeparationSettlementResource($this->whenLoaded('separationSettlement')),
            'status' => $this->status,
            'interview_due_on' => $this->interview_due_on?->toDateString(),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'separation_reason' => $this->separation_reason,
            'rehire_recommendation' => $this->rehire_recommendation,
            'overall_experience_rating' => $this->overall_experience_rating,
            'manager_relationship_rating' => $this->manager_relationship_rating,
            'workload_rating' => $this->workload_rating,
            'compensation_rating' => $this->compensation_rating,
            'public_feedback' => $this->public_feedback,
            'improvement_suggestions' => $this->improvement_suggestions,
            'confidential_responses' => $this->when($canViewConfidential, $this->confidential_responses),
            'confidential_responses_visible' => $canViewConfidential,
            'risk_flags' => $this->risk_flags ?? [],
            'questionnaire_template' => $this->questionnaire_template ?? [],
            'hr_review_notes' => $this->when($canViewConfidential, $this->hr_review_notes),
            'action_items' => $this->action_items ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'scheduled_by' => $this->whenLoaded('scheduledBy', fn () => $this->userSummary($this->scheduledBy)),
            'submitted_by' => $this->whenLoaded('submittedBy', fn () => $this->userSummary($this->submittedBy)),
            'reviewed_by' => $this->whenLoaded('reviewedBy', fn () => $this->userSummary($this->reviewedBy)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function userSummary($user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ] : null;
    }
}
