<?php

namespace App\Application\Payroll\Data;

use App\Domain\Payroll\ValueObjects\MinorMoney;

final readonly class StatutoryPayrollSimulationData
{
    /** @param array<string, mixed> $result */
    public function __construct(public array $result) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->result;
    }

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return array_replace($this->result, [
            'gross_display' => MinorMoney::fromMinor((int) ($this->result['gross_minor'] ?? 0))->toDecimal(),
            'deduction_display' => MinorMoney::fromMinor((int) ($this->result['deduction_minor'] ?? 0))->toDecimal(),
            'employer_contribution_display' => MinorMoney::fromMinor((int) ($this->result['employer_contribution_minor'] ?? 0))->toDecimal(),
            'net_display' => MinorMoney::fromMinor((int) ($this->result['net_minor'] ?? 0))->toDecimal(),
            'lines' => collect((array) ($this->result['lines'] ?? []))->map(function (array $line): array {
                return $line + [
                    'basis_display' => MinorMoney::fromMinor((int) ($line['basis_minor'] ?? 0))->toDecimal(),
                    'amount_display' => MinorMoney::fromMinor((int) ($line['amount_minor'] ?? 0))->toDecimal(),
                ];
            })->all(),
        ]);
    }
}
