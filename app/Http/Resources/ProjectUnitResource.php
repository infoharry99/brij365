<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectUnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_code' => $this->unit_code,
            'tower' => $this->tower,
            'floor' => $this->floor,
            'unit_number' => $this->unit_number,
            'unit_type' => $this->unit_type,
            'carpet_area_sqft' => (float) $this->carpet_area_sqft,
            'saleable_area_sqft' => (float) $this->saleable_area_sqft,
            'base_rate' => (float) $this->base_rate,
            'base_price' => (float) $this->base_price,
            'floor_rise' => (float) $this->floor_rise,
            'parking_charges' => (float) $this->parking_charges,
            'other_charges' => (float) $this->other_charges,
            'tax_amount' => (float) $this->tax_amount,
            'total_price' => (float) $this->total_price,
            'status' => $this->status,
            'reserved_until' => $this->reserved_until?->toISOString(),
            'is_bookable' => $this->isBookable(),
            'company' => $this->whenLoaded('company', fn () => [
                'code' => $this->company->code,
                'name' => $this->company->name,
            ]),
            'project' => $this->whenLoaded('project', fn () => [
                'code' => $this->project->code,
                'name' => $this->project->name,
                'city' => $this->project->city,
            ]),
            'active_booking' => $this->whenLoaded('activeBooking', fn () => $this->activeBooking ? [
                'booking_code' => $this->activeBooking->booking_code,
                'status' => $this->activeBooking->status,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
