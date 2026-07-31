<?php

namespace App\Application\Payroll\Data;

use App\Domain\Payroll\ValueObjects\MinorMoney;
use App\Models\Employee;
use App\Models\EmployeeTaxProfile;
use Illuminate\Support\Collection;

final readonly class EmployeeTaxProfilePageData
{
    /**
     * @param list<array{category_code: string, declaration_type: string, declared_amount: string, managed_document_id: int|null}> $declarationRows
     * @param list<array{id: int, label: string}> $proofOptions
     * @param list<array{event_label: string, at: string|null, note: string}> $workflowRows
     */
    public function __construct(
        public Employee $employee,
        public ?EmployeeTaxProfile $profile,
        public string $financialYear,
        public array $declarationRows,
        public array $proofOptions,
        public array $workflowRows,
        public string $regimeCodeInput,
        public string $previousEmployerIncomeInput,
        public string $previousEmployerTdsInput,
        public string $projectedOtherIncomeInput,
        public bool $editable,
        public bool $isLocked,
        public bool $isReadOnly,
        public bool $canSubmit,
        public string $statusLabel,
        public string $statusTone,
        public string $saveButtonLabel,
        public ?int $amendmentVersion,
        public string $checksumPrefix,
    ) {}

    /** @param Collection<int, \App\Models\ManagedDocument> $proofDocuments */
    public static function from(Employee $employee, ?EmployeeTaxProfile $profile, Collection $proofDocuments, string $financialYear): self
    {
        $payload = (array) ($profile?->input_payload ?? []);
        $rows = $profile?->declarations?->map(static fn ($declaration): array => [
            'category_code' => (string) $declaration->category_code,
            'declaration_type' => (string) $declaration->declaration_type,
            'declared_amount' => self::formatMinor((int) data_get($declaration->amount_payload, 'declared_minor', 0)),
            'managed_document_id' => $declaration->managed_document_id ? (int) $declaration->managed_document_id : null,
        ])->values()->all() ?? [];
        $targetRows = min(50, max(3, count($rows) + 1));
        while (count($rows) < $targetRows) {
            $rows[] = ['category_code' => '', 'declaration_type' => '', 'declared_amount' => '', 'managed_document_id' => null];
        }

        $editable = $profile === null || in_array($profile->status, [EmployeeTaxProfile::STATUS_DRAFT, EmployeeTaxProfile::STATUS_LOCKED], true);
        $isLocked = $profile?->status === EmployeeTaxProfile::STATUS_LOCKED;
        $statusTone = match ($profile?->status) {
            EmployeeTaxProfile::STATUS_LOCKED => 'success',
            EmployeeTaxProfile::STATUS_VERIFIED => 'info',
            EmployeeTaxProfile::STATUS_SUBMITTED => 'warning',
            default => 'muted',
        };

        return new self(
            employee: $employee,
            profile: $profile,
            financialYear: $financialYear,
            declarationRows: $rows,
            proofOptions: $proofDocuments->map(static fn ($document): array => [
                'id' => (int) $document->id,
                'label' => (string) $document->title.' · '.(string) $document->document_number,
            ])->values()->all(),
            workflowRows: collect($profile?->workflow_history ?? [])->map(static fn (array $entry): array => [
                'event_label' => str_replace('_', ' ', ucfirst((string) ($entry['event'] ?? 'updated'))),
                'at' => filled($entry['at'] ?? null) ? (string) $entry['at'] : null,
                'note' => (string) ($entry['note'] ?? '-'),
            ])->values()->all(),
            regimeCodeInput: (string) ($profile?->regime_code ?? 'DEFAULT'),
            previousEmployerIncomeInput: self::formatMinor((int) ($payload['previous_employer_income_minor'] ?? 0)),
            previousEmployerTdsInput: self::formatMinor((int) ($payload['previous_employer_tds_minor'] ?? 0)),
            projectedOtherIncomeInput: self::formatMinor((int) ($payload['projected_other_income_minor'] ?? 0)),
            editable: $editable,
            isLocked: $isLocked,
            isReadOnly: $profile !== null && ! $editable,
            canSubmit: $profile?->status === EmployeeTaxProfile::STATUS_DRAFT,
            statusLabel: $profile === null ? '' : ucfirst((string) $profile->status),
            statusTone: $statusTone,
            saveButtonLabel: $isLocked ? 'Start amendment draft' : 'Save draft',
            amendmentVersion: $isLocked ? ((int) $profile->version + 1) : null,
            checksumPrefix: $profile === null ? '' : substr((string) $profile->input_checksum, 0, 16),
        );
    }

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return [
            'employee' => $this->employee,
            'taxProfile' => $this->profile,
            'financialYear' => $this->financialYear,
            'declarationRows' => $this->declarationRows,
            'proofOptions' => $this->proofOptions,
            'workflowRows' => $this->workflowRows,
            'regimeCodeInput' => $this->regimeCodeInput,
            'previousEmployerIncomeInput' => $this->previousEmployerIncomeInput,
            'previousEmployerTdsInput' => $this->previousEmployerTdsInput,
            'projectedOtherIncomeInput' => $this->projectedOtherIncomeInput,
            'editable' => $this->editable,
            'isLocked' => $this->isLocked,
            'isReadOnly' => $this->isReadOnly,
            'canSubmit' => $this->canSubmit,
            'statusLabel' => $this->statusLabel,
            'statusTone' => $this->statusTone,
            'saveButtonLabel' => $this->saveButtonLabel,
            'amendmentVersion' => $this->amendmentVersion,
            'checksumPrefix' => $this->checksumPrefix,
        ];
    }

    private static function formatMinor(int $minor): string
    {
        if ($minor >= 0) {
            return MinorMoney::fromMinor($minor)->toDecimal();
        }

        $digits = ltrim((string) $minor, '-');
        $digits = str_pad($digits, 3, '0', STR_PAD_LEFT);

        return '-'.substr($digits, 0, -2).'.'.substr($digits, -2);
    }
}
