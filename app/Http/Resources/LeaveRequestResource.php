<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'status' => $this->status,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'duration_unit' => $this->duration_unit,
            'requested_days' => (float) $this->requested_days,
            'reason' => $this->reason,
            'decision_note' => $this->decision_note,
            'workflow_history' => $this->workflow_history,
            'decided_at' => $this->decided_at?->toISOString(),
            'employee' => $this->whenLoaded('employee', fn () => [
                'employee_code' => $this->employee->employee_code,
                'name' => $this->employee->name,
                'department' => $this->employee->department,
            ]),
            'leave_type' => $this->whenLoaded('leaveType', fn () => [
                'code' => $this->leaveType->code,
                'name' => $this->leaveType->name,
                'is_paid' => $this->leaveType->is_paid,
            ]),
            'requested_by' => $this->whenLoaded('requestedBy', fn () => $this->requestedBy ? [
                'name' => $this->requestedBy->name,
                'email' => $this->requestedBy->email,
            ] : null),
            'decided_by' => $this->whenLoaded('decidedBy', fn () => $this->decidedBy ? [
                'name' => $this->decidedBy->name,
                'email' => $this->decidedBy->email,
            ] : null),
            'supporting_document' => $this->whenLoaded('supportingDocument', fn () => $this->supportingDocument ? [
                'document_number' => $this->supportingDocument->document_number,
                'title' => $this->supportingDocument->title,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
