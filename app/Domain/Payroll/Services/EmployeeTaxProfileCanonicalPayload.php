<?php

namespace App\Domain\Payroll\Services;

use App\Models\EmployeeTaxProfile;

final class EmployeeTaxProfileCanonicalPayload
{
    /** @return array<string, mixed> */
    public function for(EmployeeTaxProfile $profile): array
    {
        $profile->loadMissing('declarations');

        return [
            'employee_id' => $profile->employee_id,
            'financial_year' => $profile->financial_year,
            'regime_code' => $profile->regime_code,
            'input_payload' => $profile->input_payload,
            'declarations' => $profile->declarations
                ->sortBy('category_code')
                ->map(fn ($declaration): array => [
                    'category_code' => $declaration->category_code,
                    'declaration_type' => $declaration->declaration_type,
                    'status' => $declaration->status,
                    'amount_payload' => $declaration->amount_payload,
                    'proof_snapshot' => data_get($declaration->metadata, 'proof_snapshot'),
                ])->values()->all(),
        ];
    }
}
