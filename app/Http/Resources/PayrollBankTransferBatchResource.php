<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollBankTransferBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canIncludePayload = $request->boolean('include_payload')
            && $request->user()?->can('viewPayload', \App\Models\PayrollBankTransferBatch::class) === true;

        return [
            'id' => $this->id,
            'batch_number' => $this->batch_number,
            'bank_name' => $this->bank_name,
            'payment_date' => $this->payment_date?->toDateString(),
            'status' => $this->status,
            'item_count' => $this->item_count,
            'control_total' => (float) $this->control_total,
            'checksum' => $this->checksum,
            'csv_payload' => $this->when($canIncludePayload, $this->csv_payload),
            'validation_summary' => $this->validation_summary ?? [],
            'workflow_history' => $this->workflow_history ?? [],
            'prepared_at' => $this->prepared_at?->toISOString(),
            'released_at' => $this->released_at?->toISOString(),
            'payroll_run' => $this->whenLoaded('payrollRun', fn (): ?array => $this->payrollRun ? [
                'id' => $this->payrollRun->id,
                'run_number' => $this->payrollRun->run_number,
                'period_year' => $this->payrollRun->period_year,
                'period_month' => $this->payrollRun->period_month,
                'status' => $this->payrollRun->status,
                'net_payable' => (float) $this->payrollRun->net_payable,
            ] : null),
            'prepared_by' => $this->whenLoaded('preparedBy', fn (): ?array => $this->preparedBy ? [
                'id' => $this->preparedBy->id,
                'name' => $this->preparedBy->name,
                'email' => $this->preparedBy->email,
            ] : null),
            'released_by' => $this->whenLoaded('releasedBy', fn (): ?array => $this->releasedBy ? [
                'id' => $this->releasedBy->id,
                'name' => $this->releasedBy->name,
                'email' => $this->releasedBy->email,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'employee_code' => $item->employee_code,
                'beneficiary_name' => $item->beneficiary_name,
                'bank_account_last4' => $item->bank_account_last4,
                'ifsc_code' => $item->ifsc_code,
                'amount' => (float) $item->amount,
                'status' => $item->status,
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
