<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveTypeResource extends JsonResource
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
            'annual_entitlement_days' => (float) $this->annual_entitlement_days,
            'is_paid' => $this->is_paid,
            'requires_document' => $this->requires_document,
            'allows_half_day' => $this->allows_half_day,
            'allow_negative_balance' => $this->allow_negative_balance,
            'carry_forward_enabled' => $this->carry_forward_enabled,
            'max_carry_forward_days' => (float) $this->max_carry_forward_days,
            'encashment_enabled' => $this->encashment_enabled,
            'approval_chain' => $this->approval_chain,
            'is_active' => $this->is_active,
        ];
    }
}
