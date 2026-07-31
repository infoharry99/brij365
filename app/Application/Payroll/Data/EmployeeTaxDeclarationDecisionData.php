<?php

namespace App\Application\Payroll\Data;

final readonly class EmployeeTaxDeclarationDecisionData
{
    public function __construct(
        public string $categoryCode,
        public string $status,
        public int $verifiedMinor,
        public ?string $decisionNote,
    ) {}

    /** @param array<string, mixed> $attributes */
    public static function fromArray(array $attributes): self
    {
        $note = trim((string) ($attributes['decision_note'] ?? ''));

        return new self(
            categoryCode: strtoupper(trim((string) $attributes['category_code'])),
            status: (string) $attributes['status'],
            verifiedMinor: (int) ($attributes['verified_minor'] ?? 0),
            decisionNote: $note === '' ? null : $note,
        );
    }
}
