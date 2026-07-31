<?php

namespace App\Application\Payroll\Data;

final readonly class EmployeeTaxProfileVerificationData
{
    /** @param list<EmployeeTaxDeclarationDecisionData> $decisions */
    public function __construct(public int $lockVersion, public array $decisions) {}

    /** @param array<string, mixed> $attributes */
    public static function fromArray(array $attributes): self
    {
        return new self(
            lockVersion: (int) $attributes['lock_version'],
            decisions: array_values(array_map(
                static fn (array $decision): EmployeeTaxDeclarationDecisionData => EmployeeTaxDeclarationDecisionData::fromArray($decision),
                $attributes['decisions'] ?? [],
            )),
        );
    }
}
