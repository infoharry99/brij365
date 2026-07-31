<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'gateway_provider' => $this->gateway_provider,
            'gateway_mode' => $this->gatewayMode($this->gateway_provider),
            'gateway_label' => $this->gatewayLabel($this->gateway_provider),
            'gateway_reference' => $this->gateway_reference,
            'status' => $this->status,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'purpose' => $this->purpose,
            'expires_at' => $this->expires_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'payment_mode' => $this->payment_mode,
            'instrument_number' => $this->instrument_number,
            'checksum' => $this->checksum,
            'payment_url' => $this->gateway_payload['payment_url'] ?? null,
            'gateway_payload' => $this->gateway_payload ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'metadata' => $this->metadata ?? [],
            'booking' => $this->whenLoaded('booking', fn (): array => [
                'id' => $this->booking->id,
                'booking_code' => $this->booking->booking_code,
                'status' => $this->booking->status,
            ]),
            'payment_schedule' => $this->whenLoaded('paymentSchedule', fn (): ?array => $this->paymentSchedule ? [
                'id' => $this->paymentSchedule->id,
                'sequence' => $this->paymentSchedule->sequence,
                'milestone' => $this->paymentSchedule->milestone,
                'amount' => (float) $this->paymentSchedule->amount,
                'due_on' => $this->paymentSchedule->due_on?->toDateString(),
                'status' => $this->paymentSchedule->status,
            ] : null),
            'customer' => $this->whenLoaded('customer', fn (): array => [
                'id' => $this->customer->id,
                'code' => $this->customer->code,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
            ]),
            'collection_receipt' => $this->whenLoaded('collectionReceipt', fn (): ?array => $this->collectionReceipt ? [
                'id' => $this->collectionReceipt->id,
                'receipt_number' => $this->collectionReceipt->receipt_number,
                'status' => $this->collectionReceipt->status,
                'amount' => (float) $this->collectionReceipt->amount,
            ] : null),
            'created_by' => $this->whenLoaded('createdBy', fn (): ?array => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'email' => $this->createdBy->email,
            ] : null),
            'paid_by' => $this->whenLoaded('paidBy', fn (): ?array => $this->paidBy ? [
                'id' => $this->paidBy->id,
                'name' => $this->paidBy->name,
                'email' => $this->paidBy->email,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function gatewayMode(?string $provider): string
    {
        return $this->isSimulatedGatewayProvider($provider) ? 'simulated' : 'configured';
    }

    private function gatewayLabel(?string $provider): string
    {
        $provider = strtolower(trim((string) $provider));

        if ($this->isSimulatedGatewayProvider($provider)) {
            return 'Internal simulated gateway';
        }

        return str($provider)->replace(['_', '-'], ' ')->title()->toString().' gateway';
    }

    private function isSimulatedGatewayProvider(?string $provider): bool
    {
        $provider = strtolower(trim((string) $provider));

        return $provider === ''
            || in_array($provider, ['prototype', 'demo', 'mock', 'sandbox', 'simulated', 'simulation'], true);
    }
}
