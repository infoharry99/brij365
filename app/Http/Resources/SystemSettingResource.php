<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scope_key' => $this->scope_key,
            'setting_group' => $this->setting_group,
            'setting_key' => $this->setting_key,
            'label' => $this->label,
            'description' => $this->description,
            'value_type' => $this->value_type,
            'value' => $this->value ?? [],
            'status' => $this->status,
            'version' => $this->version,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'workflow_history' => $this->workflow_history ?? [],
            'metadata' => $this->metadata ?? [],
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
            'statutory_verification' => $this->whenLoaded('statutoryVerification', function (): ?array {
                $verification = $this->statutoryVerification;

                return $verification ? [
                    'configuration_checksum' => $verification->configuration_checksum,
                    'verified_at' => $verification->verified_at?->toISOString(),
                    'verified_by' => $verification->relationLoaded('verifiedBy') && $verification->verifiedBy ? [
                        'id' => $verification->verifiedBy->id,
                        'name' => $verification->verifiedBy->name,
                    ] : null,
                ] : null;
            }),
        ];
    }
}
