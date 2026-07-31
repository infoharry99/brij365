<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceDueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'due_number' => $this->due_number,
            'period_start_on' => $this->period_start_on?->toDateString(),
            'period_end_on' => $this->period_end_on?->toDateString(),
            'due_on' => $this->due_on?->toDateString(),
            'amount' => (float) $this->amount,
            'paid_amount' => (float) $this->paid_amount,
            'balance_amount' => (float) $this->balance_amount,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->toISOString(),
            'payment_reference' => $this->payment_reference,
            'last_reminded_at' => $this->last_reminded_at?->toISOString(),
            'workflow_history' => $this->workflow_history ?? [],
            'project' => $this->whenLoaded('project', fn (): array => [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ]),
            'booking' => $this->whenLoaded('booking', fn (): ?array => $this->booking ? [
                'id' => $this->booking->id,
                'booking_code' => $this->booking->booking_code,
            ] : null),
            'customer' => $this->whenLoaded('customer', fn (): array => [
                'id' => $this->customer->id,
                'code' => $this->customer->code,
                'name' => $this->customer->name,
            ]),
            'unit' => $this->whenLoaded('unit', fn (): ?array => $this->unit ? [
                'id' => $this->unit->id,
                'unit_code' => $this->unit->unit_code,
                'unit_number' => $this->unit->unit_number,
            ] : null),
        ];
    }
}
