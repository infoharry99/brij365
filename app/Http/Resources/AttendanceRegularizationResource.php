<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRegularizationResource extends JsonResource
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
            'work_date' => $this->work_date?->toDateString(),
            'requested_check_in_at' => $this->requested_check_in_at?->toISOString(),
            'requested_check_out_at' => $this->requested_check_out_at?->toISOString(),
            'reason' => $this->reason,
            'decision_note' => $this->decision_note,
            'workflow_history' => $this->workflow_history,
            'decided_at' => $this->decided_at?->toISOString(),
            'employee' => $this->whenLoaded('employee', fn () => [
                'employee_code' => $this->employee->employee_code,
                'name' => $this->employee->name,
                'department' => $this->employee->department,
            ]),
            'attendance_record' => $this->whenLoaded('attendanceRecord', fn () => $this->attendanceRecord ? [
                'status' => $this->attendanceRecord->status,
                'late_minutes' => $this->attendanceRecord->late_minutes,
                'early_leave_minutes' => $this->attendanceRecord->early_leave_minutes,
                'worked_minutes' => $this->attendanceRecord->worked_minutes,
            ] : null),
            'requested_by' => $this->whenLoaded('requestedBy', fn () => $this->requestedBy ? [
                'name' => $this->requestedBy->name,
                'email' => $this->requestedBy->email,
            ] : null),
            'decided_by' => $this->whenLoaded('decidedBy', fn () => $this->decidedBy ? [
                'name' => $this->decidedBy->name,
                'email' => $this->decidedBy->email,
            ] : null),
        ];
    }
}
