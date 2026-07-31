<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_date' => $this->work_date?->toDateString(),
            'check_in_at' => $this->check_in_at?->toISOString(),
            'check_out_at' => $this->check_out_at?->toISOString(),
            'source' => $this->source,
            'status' => $this->status,
            'late_minutes' => $this->late_minutes,
            'early_leave_minutes' => $this->early_leave_minutes,
            'worked_minutes' => $this->worked_minutes,
            'metadata' => $this->metadata,
            'employee' => $this->whenLoaded('employee', fn () => [
                'employee_code' => $this->employee->employee_code,
                'name' => $this->employee->name,
                'department' => $this->employee->department,
            ]),
            'shift' => $this->whenLoaded('shift', fn () => $this->shift ? [
                'code' => $this->shift->code,
                'name' => $this->shift->name,
                'starts_at' => $this->shift->starts_at,
                'ends_at' => $this->shift->ends_at,
            ] : null),
        ];
    }
}
