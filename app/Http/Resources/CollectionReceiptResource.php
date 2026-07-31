<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'status' => $this->status,
            'receipt_date' => $this->receipt_date?->toDateString(),
            'payment_mode' => $this->payment_mode,
            'instrument_number' => $this->instrument_number,
            'bank_name' => $this->bank_name,
            'amount' => (float) $this->amount,
            'tax_deducted_amount' => (float) $this->tax_deducted_amount,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'approved_at' => $this->approved_at?->toISOString(),
            'company' => $this->whenLoaded('company', fn () => [
                'code' => $this->company->code,
                'name' => $this->company->name,
            ]),
            'project' => $this->whenLoaded('project', fn () => [
                'code' => $this->project->code,
                'name' => $this->project->name,
            ]),
            'booking' => $this->whenLoaded('booking', fn () => [
                'booking_code' => $this->booking->booking_code,
                'status' => $this->booking->status,
                'net_receivable' => (float) $this->booking->net_receivable,
            ]),
            'payment_schedule' => $this->whenLoaded('paymentSchedule', fn () => $this->paymentSchedule ? [
                'sequence' => $this->paymentSchedule->sequence,
                'milestone' => $this->paymentSchedule->milestone,
                'amount' => (float) $this->paymentSchedule->amount,
                'status' => $this->paymentSchedule->status,
            ] : null),
            'customer' => $this->whenLoaded('customer', fn () => [
                'code' => $this->customer->code,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
                'phone' => $this->customer->phone,
            ]),
            'collected_by' => $this->whenLoaded('collectedBy', fn () => $this->collectedBy ? [
                'name' => $this->collectedBy->name,
                'email' => $this->collectedBy->email,
            ] : null),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy ? [
                'name' => $this->approvedBy->name,
                'email' => $this->approvedBy->email,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
