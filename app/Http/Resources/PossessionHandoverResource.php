<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PossessionHandoverResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'handover_number' => $this->handover_number,
            'target_handover_on' => $this->target_handover_on?->toDateString(),
            'actual_handover_on' => $this->actual_handover_on?->toDateString(),
            'status' => $this->status,
            'financial_outstanding' => (float) $this->financial_outstanding,
            'checklist' => $this->checklist ?? [],
            'blockers' => $this->blockers ?? [],
            'possession_letter_reference' => $this->possession_letter_reference,
            'workflow_history' => $this->workflow_history ?? [],
            'completed_at' => $this->completed_at?->toISOString(),
            'booking' => $this->whenLoaded('booking', fn (): array => [
                'id' => $this->booking->id,
                'booking_code' => $this->booking->booking_code,
                'status' => $this->booking->status,
                'net_receivable' => (float) $this->booking->net_receivable,
            ]),
            'project' => $this->whenLoaded('project', fn (): array => [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ]),
            'unit' => $this->whenLoaded('unit', fn (): array => [
                'id' => $this->unit->id,
                'unit_code' => $this->unit->unit_code,
                'unit_number' => $this->unit->unit_number,
                'status' => $this->unit->status,
            ]),
            'customer' => $this->whenLoaded('customer', fn (): array => [
                'id' => $this->customer->id,
                'code' => $this->customer->code,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
            ]),
            'initiated_by' => $this->whenLoaded('initiatedBy', fn (): ?array => $this->initiatedBy ? [
                'id' => $this->initiatedBy->id,
                'name' => $this->initiatedBy->name,
                'email' => $this->initiatedBy->email,
            ] : null),
            'completed_by' => $this->whenLoaded('completedBy', fn (): ?array => $this->completedBy ? [
                'id' => $this->completedBy->id,
                'name' => $this->completedBy->name,
                'email' => $this->completedBy->email,
            ] : null),
            'snags' => HandoverSnagResource::collection($this->whenLoaded('snags')),
        ];
    }
}
