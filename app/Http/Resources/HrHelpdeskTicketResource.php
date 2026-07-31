<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HrHelpdeskTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'category' => $this->category,
            'priority' => $this->priority,
            'status' => $this->status,
            'subject' => $this->subject,
            'description' => $this->description,
            'resolution_summary' => $this->resolution_summary,
            'attachments' => $this->attachments ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'resolved_at' => $this->resolved_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'employee_code' => $this->employee->employee_code,
                'name' => $this->employee->name,
                'department' => $this->employee->department,
            ]),
            'raised_by' => $this->whenLoaded('raisedBy', fn () => $this->raisedBy ? ['name' => $this->raisedBy->name, 'email' => $this->raisedBy->email] : null),
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo ? ['name' => $this->assignedTo->name, 'email' => $this->assignedTo->email] : null),
            'closed_by' => $this->whenLoaded('closedBy', fn () => $this->closedBy ? ['name' => $this->closedBy->name, 'email' => $this->closedBy->email] : null),
        ];
    }
}
