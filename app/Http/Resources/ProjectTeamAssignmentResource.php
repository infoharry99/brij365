<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectTeamAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'project_id' => $this->project_id,
            'user_id' => $this->user_id,
            'employee_id' => $this->employee_id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'employee_code' => $this->employee?->employee_code,
            'role' => $this->role_label,
            'role_label' => $this->role_label,
            'dept' => $this->department,
            'department' => $this->department,
            'access' => $this->access_level,
            'access_level' => $this->access_level,
            'status' => $this->status,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'notes' => $this->notes,
            'assigned_by' => $this->assignedBy ? [
                'id' => $this->assignedBy->id,
                'name' => $this->assignedBy->name,
            ] : null,
            'revoked_by' => $this->revokedBy ? [
                'id' => $this->revokedBy->id,
                'name' => $this->revokedBy->name,
            ] : null,
            'revoked_at' => $this->revoked_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
