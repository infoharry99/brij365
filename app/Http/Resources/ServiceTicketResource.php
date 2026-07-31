<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'category' => $this->category,
            'priority' => $this->priority,
            'source' => $this->source,
            'subject' => $this->subject,
            'description' => $this->description,
            'status' => $this->status,
            'first_response_due_at' => $this->first_response_due_at?->toISOString(),
            'first_responded_at' => $this->first_responded_at?->toISOString(),
            'sla_due_at' => $this->sla_due_at?->toISOString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'resolution_summary' => $this->resolution_summary,
            'customer_rating' => $this->customer_rating,
            'attachments' => $this->attachments ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'metadata' => $this->metadata ?? [],
            'booking' => $this->whenLoaded('booking', fn (): ?array => $this->booking ? [
                'id' => $this->booking->id,
                'booking_code' => $this->booking->booking_code,
                'status' => $this->booking->status,
            ] : null),
            'project' => $this->whenLoaded('project', fn (): ?array => $this->project ? [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ] : null),
            'unit' => $this->whenLoaded('unit', fn (): ?array => $this->unit ? [
                'id' => $this->unit->id,
                'unit_code' => $this->unit->unit_code,
                'unit_number' => $this->unit->unit_number,
                'status' => $this->unit->status,
            ] : null),
            'customer' => $this->whenLoaded('customer', fn (): array => [
                'id' => $this->customer->id,
                'code' => $this->customer->code,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
            ]),
            'raised_by' => $this->whenLoaded('raisedBy', fn (): ?array => $this->raisedBy ? [
                'id' => $this->raisedBy->id,
                'name' => $this->raisedBy->name,
                'email' => $this->raisedBy->email,
            ] : null),
            'assigned_to' => $this->whenLoaded('assignedTo', fn (): ?array => $this->assignedTo ? [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
                'email' => $this->assignedTo->email,
            ] : null),
            'closed_by' => $this->whenLoaded('closedBy', fn (): ?array => $this->closedBy ? [
                'id' => $this->closedBy->id,
                'name' => $this->closedBy->name,
                'email' => $this->closedBy->email,
            ] : null),
            'work_orders' => MaintenanceWorkOrderResource::collection($this->whenLoaded('workOrders')),
        ];
    }
}
