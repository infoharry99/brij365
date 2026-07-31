<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'po_number' => $this->po_number,
            'po_date' => $this->po_date?->toDateString(),
            'expected_delivery_on' => $this->expected_delivery_on?->toDateString(),
            'status' => $this->status,
            'payment_terms' => $this->payment_terms,
            'items' => $this->items ?? [],
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'total_amount' => (float) $this->total_amount,
            'terms' => $this->terms,
            'workflow_history' => $this->workflow_history ?? [],
            'approved_at' => $this->approved_at?->toISOString(),
            'project' => $this->whenLoaded('project', fn (): array => [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ]),
            'vendor' => new VendorResource($this->whenLoaded('vendor')),
            'purchase_requisition' => $this->whenLoaded('purchaseRequisition', fn (): ?array => $this->purchaseRequisition ? [
                'id' => $this->purchaseRequisition->id,
                'requisition_number' => $this->purchaseRequisition->requisition_number,
                'status' => $this->purchaseRequisition->status,
            ] : null),
            'created_by' => $this->whenLoaded('createdBy', fn (): ?array => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'email' => $this->createdBy->email,
            ] : null),
            'approved_by' => $this->whenLoaded('approvedBy', fn (): ?array => $this->approvedBy ? [
                'id' => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
                'email' => $this->approvedBy->email,
            ] : null),
            'goods_receipts' => GoodsReceiptResource::collection($this->whenLoaded('goodsReceipts')),
        ];
    }
}
