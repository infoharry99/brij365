<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grn_number' => $this->grn_number,
            'received_on' => $this->received_on?->toDateString(),
            'delivery_challan_number' => $this->delivery_challan_number,
            'status' => $this->status,
            'items' => $this->items ?? [],
            'accepted_total' => (float) $this->accepted_total,
            'quality_notes' => $this->quality_notes,
            'metadata' => $this->metadata ?? [],
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn (): array => [
                'id' => $this->purchaseOrder->id,
                'po_number' => $this->purchaseOrder->po_number,
                'status' => $this->purchaseOrder->status,
            ]),
            'project' => $this->whenLoaded('project', fn (): array => [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ]),
            'received_by' => $this->whenLoaded('receivedBy', fn (): ?array => $this->receivedBy ? [
                'id' => $this->receivedBy->id,
                'name' => $this->receivedBy->name,
                'email' => $this->receivedBy->email,
            ] : null),
            'stock_movements' => StockMovementResource::collection($this->whenLoaded('stockMovements')),
        ];
    }
}
