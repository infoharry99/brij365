<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeAssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_code' => $this->asset_code,
            'category' => $this->category,
            'name' => $this->name,
            'serial_number' => $this->serial_number,
            'status' => $this->status,
            'condition' => $this->condition,
            'assigned_on' => $this->assigned_on?->toDateString(),
            'recovered_on' => $this->recovered_on?->toDateString(),
            'estimated_value' => (float) $this->estimated_value,
            'metadata' => $this->metadata ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'company' => $this->whenLoaded('company', fn (): ?array => $this->company ? [
                'id' => $this->company->id,
                'code' => $this->company->code,
                'name' => $this->company->name,
            ] : null),
            'employee' => $this->whenLoaded('employee', fn (): ?array => $this->employee ? [
                'id' => $this->employee->id,
                'employee_code' => $this->employee->employee_code,
                'name' => $this->employee->name,
                'department' => $this->employee->department,
                'designation' => $this->employee->designation,
            ] : null),
            'assigned_by' => $this->whenLoaded('assignedBy', fn (): ?array => $this->assignedBy ? [
                'id' => $this->assignedBy->id,
                'name' => $this->assignedBy->name,
                'email' => $this->assignedBy->email,
            ] : null),
            'recovered_by' => $this->whenLoaded('recoveredBy', fn (): ?array => $this->recoveredBy ? [
                'id' => $this->recoveredBy->id,
                'name' => $this->recoveredBy->name,
                'email' => $this->recoveredBy->email,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
