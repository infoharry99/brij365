<?php

namespace App\Application\Payroll\Data;

final readonly class EmployeeTaxProfileDraftData
{
    /** @param list<EmployeeTaxDeclarationDraftData> $declarations */
    public function __construct(
        public int $employeeId,
        public string $financialYear,
        public string $regimeCode,
        public ?int $lockVersion,
        public int $previousEmployerIncomeMinor,
        public int $previousEmployerTdsMinor,
        public int $projectedOtherIncomeMinor,
        public array $declarations,
    ) {}

    /** @param array<string, mixed> $attributes */
    public static function fromArray(array $attributes): self
    {
        return new self(
            employeeId: (int) $attributes['employee_id'],
            financialYear: (string) $attributes['financial_year'],
            regimeCode: strtoupper(trim((string) $attributes['regime_code'])),
            lockVersion: isset($attributes['lock_version']) ? (int) $attributes['lock_version'] : null,
            previousEmployerIncomeMinor: (int) ($attributes['previous_employer_income_minor'] ?? 0),
            previousEmployerTdsMinor: (int) ($attributes['previous_employer_tds_minor'] ?? 0),
            projectedOtherIncomeMinor: (int) ($attributes['projected_other_income_minor'] ?? 0),
            declarations: array_values(array_map(
                static fn (array $declaration): EmployeeTaxDeclarationDraftData => EmployeeTaxDeclarationDraftData::fromArray($declaration),
                $attributes['declarations'] ?? [],
            )),
        );
    }
}
