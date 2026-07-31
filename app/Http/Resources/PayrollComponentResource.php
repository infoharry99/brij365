<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollComponentResource extends JsonResource
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
            'component_type' => $this->component_type,
            'calculation_type' => $this->calculation_type,
            'is_taxable' => $this->is_taxable,
            'is_statutory' => $this->is_statutory,
            'rules' => $this->rules,
            'is_active' => $this->is_active,
        ];
    }
}
