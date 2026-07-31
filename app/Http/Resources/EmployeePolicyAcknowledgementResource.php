<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeePolicyAcknowledgementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'employee_id' => $this->employee_id,
            'policy_key' => $this->policy_key,
            'policy_title' => $this->policy_title,
            'policy_version' => $this->policy_version,
            'status' => $this->status,
            'acknowledgement_note' => $this->acknowledgement_note,
            'policy_snapshot' => $this->policy_snapshot ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'acknowledged_at' => $this->acknowledged_at?->toISOString(),
            'employee' => $this->whenLoaded('employee', fn (): ?array => $this->employee ? [
                'id' => $this->employee->id,
                'employee_code' => $this->employee->employee_code,
                'name' => $this->employee->name,
                'department' => $this->employee->department,
            ] : null),
            'acknowledged_by' => $this->whenLoaded('acknowledgedBy', fn (): ?array => $this->acknowledgedBy ? [
                'id' => $this->acknowledgedBy->id,
                'name' => $this->acknowledgedBy->name,
                'email' => $this->acknowledgedBy->email,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
