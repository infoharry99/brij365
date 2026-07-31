<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceWorkOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_order_number' => $this->work_order_number,
            'status' => $this->status,
            'scheduled_on' => $this->scheduled_on?->toDateString(),
            'scope_of_work' => $this->scope_of_work,
            'estimated_cost' => (float) $this->estimated_cost,
            'actual_cost' => (float) $this->actual_cost,
            'materials_required' => $this->materials_required ?? [],
            'completion_notes' => $this->completion_notes,
            'completed_at' => $this->completed_at?->toISOString(),
            'workflow_history' => $this->workflow_history ?? [],
            'service_ticket' => $this->whenLoaded('serviceTicket', fn (): array => [
                'id' => $this->serviceTicket->id,
                'ticket_number' => $this->serviceTicket->ticket_number,
                'status' => $this->serviceTicket->status,
                'priority' => $this->serviceTicket->priority,
            ]),
            'unit' => $this->whenLoaded('unit', fn (): ?array => $this->unit ? [
                'id' => $this->unit->id,
                'unit_code' => $this->unit->unit_code,
                'unit_number' => $this->unit->unit_number,
            ] : null),
            'assigned_to' => $this->whenLoaded('assignedTo', fn (): ?array => $this->assignedTo ? [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
                'email' => $this->assignedTo->email,
            ] : null),
            'vendor' => $this->whenLoaded('vendor', fn (): ?array => $this->vendor ? [
                'id' => $this->vendor->id,
                'vendor_code' => $this->vendor->vendor_code,
                'name' => $this->vendor->name,
            ] : null),
        ];
    }
}
