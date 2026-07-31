<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractorMeasurementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'measurement_number' => $this->measurement_number,
            'measurement_date' => $this->measurement_date?->toDateString(),
            'bill_reference' => $this->bill_reference,
            'status' => $this->status,
            'measured_total' => (float) $this->measured_total,
            'certified_total' => (float) $this->certified_total,
            'lines' => $this->lines ?? [],
            'remarks' => $this->remarks,
            'workflow_history' => $this->workflow_history ?? [],
            'approved_at' => $this->approved_at?->toISOString(),
            'project' => $this->whenLoaded('project', fn (): array => [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ]),
            'vendor' => $this->whenLoaded('vendor', fn (): array => [
                'id' => $this->vendor->id,
                'vendor_code' => $this->vendor->vendor_code,
                'name' => $this->vendor->name,
            ]),
            'submitted_by' => $this->whenLoaded('submittedBy', fn (): ?array => $this->submittedBy ? [
                'id' => $this->submittedBy->id,
                'name' => $this->submittedBy->name,
                'email' => $this->submittedBy->email,
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
