<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'role' => $this->whenLoaded('role', fn (): ?array => $this->role ? [
                'id' => $this->role->id,
                'slug' => $this->role->slug,
                'name' => $this->role->name,
                'scope_level' => $this->role->scope_level,
                'permissions' => $this->role->permissions ?? [],
            ] : null),
            'company' => $this->whenLoaded('company', fn (): ?array => $this->company ? [
                'id' => $this->company->id,
                'code' => $this->company->code,
                'name' => $this->company->name,
                'state' => $this->company->state,
                'status' => $this->company->status,
            ] : null),
            'employee' => $this->whenLoaded('employee', fn (): ?array => $this->employee ? [
                'id' => $this->employee->id,
                'employee_code' => $this->employee->employee_code,
                'designation' => $this->employee->designation,
                'department' => $this->employee->department,
                'status' => $this->employee->status,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
