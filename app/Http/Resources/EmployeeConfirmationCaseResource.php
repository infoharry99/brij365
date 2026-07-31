<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeConfirmationCaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'case_number' => $this->case_number,
            'status' => $this->status,
            'probation_starts_on' => $this->probation_starts_on?->toDateString(),
            'probation_ends_on' => $this->probation_ends_on?->toDateString(),
            'review_due_on' => $this->review_due_on?->toDateString(),
            'manager_recommendation' => $this->manager_recommendation,
            'manager_comments' => $this->manager_comments,
            'review_scores' => $this->review_scores ?? [],
            'hr_decision' => $this->hr_decision,
            'hr_comments' => $this->hr_comments,
            'confirmation_effective_on' => $this->confirmation_effective_on?->toDateString(),
            'extended_until' => $this->extended_until?->toDateString(),
            'confirmation_letter_reference' => $this->confirmation_letter_reference,
            'workflow_history' => $this->workflow_history ?? [],
            'manager_submitted_at' => $this->manager_submitted_at?->toISOString(),
            'hr_decided_at' => $this->hr_decided_at?->toISOString(),
            'employee' => $this->whenLoaded('employee', fn (): ?array => $this->employee ? [
                'id' => $this->employee->id,
                'employee_code' => $this->employee->employee_code,
                'name' => $this->employee->name,
                'designation' => $this->employee->designation,
                'department' => $this->employee->department,
                'status' => $this->employee->status,
            ] : null),
            'manager' => $this->whenLoaded('managerEmployee', fn (): ?array => $this->managerEmployee ? [
                'id' => $this->managerEmployee->id,
                'employee_code' => $this->managerEmployee->employee_code,
                'name' => $this->managerEmployee->name,
            ] : null),
            'created_by' => $this->whenLoaded('createdBy', fn (): ?array => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'email' => $this->createdBy->email,
            ] : null),
            'manager_reviewer' => $this->whenLoaded('managerReviewer', fn (): ?array => $this->managerReviewer ? [
                'id' => $this->managerReviewer->id,
                'name' => $this->managerReviewer->name,
                'email' => $this->managerReviewer->email,
            ] : null),
            'hr_reviewer' => $this->whenLoaded('hrReviewer', fn (): ?array => $this->hrReviewer ? [
                'id' => $this->hrReviewer->id,
                'name' => $this->hrReviewer->name,
                'email' => $this->hrReviewer->email,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
