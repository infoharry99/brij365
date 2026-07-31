<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_type' => $this->store_type,
            'item_code' => $this->item_code,
            'description' => $this->description,
            'unit' => $this->unit,
            'on_hand_quantity' => (float) $this->on_hand_quantity,
            'stock_value' => (float) $this->stock_value,
            'average_rate' => (float) $this->average_rate,
            'minimum_stock_quantity' => (float) $this->minimum_stock_quantity,
            'is_below_minimum' => $this->isBelowMinimum(),
            'status' => $this->status,
            'last_movement_at' => $this->last_movement_at?->toISOString(),
            'metadata' => $this->metadata ?? [],
            'project' => $this->whenLoaded('project', fn (): array => [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ]),
            'movements' => StockMovementResource::collection($this->whenLoaded('movements')),
        ];
    }
}
