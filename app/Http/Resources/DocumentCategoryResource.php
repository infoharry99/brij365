<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'owner_type' => $this->owner_type,
            'expiry_required' => $this->expiry_required,
            'reminder_days_before_expiry' => $this->reminder_days_before_expiry,
            'retention_years' => $this->retention_years,
            'is_active' => $this->is_active,
            'company' => $this->whenLoaded('company', fn () => $this->company ? [
                'code' => $this->company->code,
                'name' => $this->company->name,
            ] : null),
        ];
    }
}
