<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'movement_number' => $this->movement_number,
            'movement_type' => $this->movement_type,
            'movement_date' => $this->movement_date?->toDateString(),
            'store_type' => $this->store_type,
            'item_code' => $this->item_code,
            'description' => $this->description,
            'unit' => $this->unit,
            'quantity' => (float) $this->quantity,
            'rate' => (float) $this->rate,
            'amount' => (float) $this->amount,
            'balance_after_quantity' => (float) $this->balance_after_quantity,
            'balance_after_value' => (float) $this->balance_after_value,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'metadata' => $this->metadata ?? [],
            'stock_item' => $this->whenLoaded('stockItem', fn (): ?array => $this->stockItem ? [
                'id' => $this->stockItem->id,
                'item_code' => $this->stockItem->item_code,
                'on_hand_quantity' => (float) $this->stockItem->on_hand_quantity,
                'stock_value' => (float) $this->stockItem->stock_value,
            ] : null),
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn (): ?array => $this->purchaseOrder ? [
                'id' => $this->purchaseOrder->id,
                'po_number' => $this->purchaseOrder->po_number,
            ] : null),
            'goods_receipt' => $this->whenLoaded('goodsReceipt', fn (): ?array => $this->goodsReceipt ? [
                'id' => $this->goodsReceipt->id,
                'grn_number' => $this->goodsReceipt->grn_number,
            ] : null),
        ];
    }
}
