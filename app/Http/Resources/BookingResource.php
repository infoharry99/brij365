<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_code' => $this->booking_code,
            'status' => $this->status,
            'booked_on' => $this->booked_on?->toDateString(),
            'agreement_value' => (float) $this->agreement_value,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount' => (float) $this->tax_amount,
            'net_receivable' => (float) $this->net_receivable,
            'booking_amount' => (float) $this->booking_amount,
            'commercials' => $this->commercials,
            'company' => $this->whenLoaded('company', fn () => [
                'code' => $this->company->code,
                'name' => $this->company->name,
            ]),
            'project' => $this->whenLoaded('project', fn () => [
                'code' => $this->project->code,
                'name' => $this->project->name,
                'city' => $this->project->city,
            ]),
            'unit' => $this->whenLoaded('unit', fn () => [
                'unit_code' => $this->unit->unit_code,
                'tower' => $this->unit->tower,
                'floor' => $this->unit->floor,
                'unit_number' => $this->unit->unit_number,
                'unit_type' => $this->unit->unit_type,
                'total_price' => (float) $this->unit->total_price,
            ]),
            'unit_price_version' => $this->whenLoaded('unitPriceVersion', fn () => $this->unitPriceVersion ? [
                'price_code' => $this->unitPriceVersion->price_code,
                'version_number' => $this->unitPriceVersion->version_number,
                'effective_from' => $this->unitPriceVersion->effective_from?->toDateString(),
                'effective_to' => $this->unitPriceVersion->effective_to?->toDateString(),
            ] : null),
            'customer' => $this->whenLoaded('customer', fn () => [
                'code' => $this->customer->code,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
            ]),
            'lead' => $this->whenLoaded('lead', fn () => $this->lead ? [
                'lead_code' => $this->lead->lead_code,
                'stage' => $this->lead->stage,
                'status' => $this->lead->status,
            ] : null),
            'partner' => $this->whenLoaded('partner', fn () => $this->partner ? [
                'code' => $this->partner->code,
                'name' => $this->partner->name,
                'type' => $this->partner->partner_type,
            ] : null),
            'booked_by' => $this->whenLoaded('bookedBy', fn () => $this->bookedBy ? [
                'name' => $this->bookedBy->name,
                'email' => $this->bookedBy->email,
            ] : null),
            'payment_schedules' => $this->whenLoaded('paymentSchedules', fn () => $this->paymentSchedules->map(fn ($schedule) => [
                'sequence' => $schedule->sequence,
                'milestone' => $schedule->milestone,
                'percentage' => (float) $schedule->percentage,
                'amount' => (float) $schedule->amount,
                'due_on' => $schedule->due_on?->toDateString(),
                'status' => $schedule->status,
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
