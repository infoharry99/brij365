<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceShiftResource extends JsonResource
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
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'is_overnight' => $this->is_overnight,
            'late_grace_minutes' => $this->late_grace_minutes,
            'early_leave_grace_minutes' => $this->early_leave_grace_minutes,
            'half_day_threshold_minutes' => $this->half_day_threshold_minutes,
            'full_day_threshold_minutes' => $this->full_day_threshold_minutes,
            'rules' => $this->rules,
            'segments' => $this->whenLoaded('segments', fn () => $this->segments->map(fn ($segment): array => [
                'id' => $segment->id,
                'sequence' => $segment->sequence,
                'label' => $segment->label,
                'starts_at' => $segment->starts_at,
                'ends_at' => $segment->ends_at,
            ])->values()->all()),
            'is_active' => $this->is_active,
        ];
    }
}
