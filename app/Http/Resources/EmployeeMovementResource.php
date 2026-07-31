<?php

namespace App\Http\Resources;

use App\Domain\Hr\Services\EmployeeMovementPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();
        $employee = $this->resource->relationLoaded('employee')
            ? $this->resource->getRelation('employee')
            : null;
        $presenter = app(EmployeeMovementPresenter::class);

        return [
            'id' => $this->id,
            'movement_number' => $this->movement_number,
            'movement_type' => $this->movement_type,
            'effective_on' => $this->effective_on?->toDateString(),
            'status' => $this->status,
            'previous_values' => $presenter->resourceValues($this->previous_values ?? [], $actor, $employee),
            'new_values' => $presenter->resourceValues($this->new_values ?? [], $actor, $employee),
            'reason' => $this->reason,
            'remarks' => $this->remarks,
            'workflow_history' => $this->workflow_history ?? [],
            'approved_at' => $this->approved_at?->toISOString(),
            'employee' => $this->whenLoaded('employee', fn (): ?array => $this->employee ? [
                'id' => $this->employee->id,
                'employee_code' => $this->employee->employee_code,
                'name' => $this->employee->name,
                'designation' => $this->employee->designation,
                'department' => $this->employee->department,
            ] : null),
            'company' => $this->whenLoaded('company', fn (): ?array => $this->company ? [
                'id' => $this->company->id,
                'code' => $this->company->code,
                'name' => $this->company->name,
            ] : null),
            'created_by' => $this->whenLoaded('createdBy', fn (): ?array => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'email' => $this->createdBy->email,
            ] : null),
            'approved_by' => $this->whenLoaded('approvedBy', fn (): ?array => $this->approvedBy ? [
                'id' => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
                'email' => $this->approvedBy->email,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
