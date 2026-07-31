<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeTaxDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $actor = $request->user();
        $isSelfServiceOnly = $actor?->hasPermission('employee.self_service') === true
            && $actor->hasPermission('payroll.view') !== true
            && $actor->hasPermission('payroll.manage') !== true
            && $actor->hasPermission('payroll.approve') !== true
            && $actor->hasPermission('compliance.view') !== true
            && $actor->hasPermission('compliance.manage') !== true
            && $actor->hasPermission('*') !== true;

        return [
            'id' => $this->id,
            'document_number' => $this->document_number,
            'document_type' => $this->document_type,
            'financial_year' => $this->financial_year,
            'assessment_year' => $this->assessment_year,
            'version' => $this->version,
            'status' => $this->status,
            'gross_salary' => (float) $this->gross_salary,
            'taxable_income' => (float) $this->taxable_income,
            'tds_deducted' => (float) $this->tds_deducted,
            'net_salary_paid' => (float) $this->net_salary_paid,
            'payroll_run_ids' => $this->when(! $isSelfServiceOnly, $this->payroll_run_ids ?? []),
            'component_summary' => $this->component_summary ?? [],
            'tax_configuration_snapshot' => $this->when(! $isSelfServiceOnly, $this->tax_configuration_snapshot ?? []),
            'document_payload' => $this->when(
                ! $isSelfServiceOnly || in_array($this->status, ['issued', 'acknowledged'], true),
                fn () => $isSelfServiceOnly ? $this->employeeFacingPayload($this->document_payload ?? []) : ($this->document_payload ?? []),
            ),
            'issue_reference' => $this->issue_reference,
            'employee_acknowledgement_note' => $this->employee_acknowledgement_note,
            'workflow_history' => $this->when(! $isSelfServiceOnly, $this->workflow_history ?? []),
            'generated_at' => $this->generated_at?->toISOString(),
            'issued_at' => $this->issued_at?->toISOString(),
            'acknowledged_at' => $this->acknowledged_at?->toISOString(),
            'employee' => $this->whenLoaded('employee', fn (): ?array => $this->employee ? [
                'id' => $this->employee->id,
                'employee_code' => $this->employee->employee_code,
                'name' => $this->employee->name,
                'designation' => $this->employee->designation,
                'department' => $this->employee->department,
                'status' => $this->employee->status,
            ] : null),
            'generated_by' => $this->whenLoaded('generatedBy', fn () => $this->userSummary($this->generatedBy)),
            'issued_by' => $this->whenLoaded('issuedBy', fn () => $this->userSummary($this->issuedBy)),
            'acknowledged_by' => $this->whenLoaded('acknowledgedBy', fn () => $this->userSummary($this->acknowledgedBy)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function userSummary($user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ] : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function employeeFacingPayload(array $payload): array
    {
        unset($payload['tax_setting']);

        return $payload;
    }
}
