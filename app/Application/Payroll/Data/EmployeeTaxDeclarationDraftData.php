<?php

namespace App\Application\Payroll\Data;

final readonly class EmployeeTaxDeclarationDraftData
{
    public function __construct(
        public string $categoryCode,
        public string $declarationType,
        public int $declaredMinor,
        public ?int $managedDocumentId,
    ) {}

    /** @param array<string, mixed> $attributes */
    public static function fromArray(array $attributes): self
    {
        return new self(
            categoryCode: strtoupper(trim((string) $attributes['category_code'])),
            declarationType: (string) $attributes['declaration_type'],
            declaredMinor: (int) $attributes['declared_minor'],
            managedDocumentId: isset($attributes['managed_document_id']) ? (int) $attributes['managed_document_id'] : null,
        );
    }
}
