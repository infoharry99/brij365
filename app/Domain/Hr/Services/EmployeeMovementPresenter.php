<?php

namespace App\Domain\Hr\Services;

use App\Application\Hr\Data\EmployeeMovementChangeData;
use App\Application\Hr\Data\EmployeeMovementRowData;
use App\Models\Employee;
use App\Models\EmployeeMovement;
use App\Models\User;

final class EmployeeMovementPresenter
{
    private const COMPENSATION_KEY = 'monthly_ctc';

    public function __construct(private readonly EmployeeFieldVisibility $fieldVisibility) {}

    public function row(EmployeeMovement $movement, Employee $employee, User $actor): EmployeeMovementRowData
    {
        $canViewCompensation = $this->fieldVisibility->canViewCompensation($actor, $employee);
        $values = (array) ($movement->new_values ?? []);
        $hasRestrictedCompensation = ! $canViewCompensation && array_key_exists(self::COMPENSATION_KEY, $values);

        if ($hasRestrictedCompensation) {
            unset($values[self::COMPENSATION_KEY]);
        }

        return new EmployeeMovementRowData(
            id: (int) $movement->id,
            number: (string) $movement->movement_number,
            type: (string) $movement->movement_type,
            typeLabel: $this->label((string) $movement->movement_type),
            effectiveDate: $movement->effective_on?->format('d M Y') ?? 'Not scheduled',
            reason: (string) ($movement->reason ?? ''),
            changes: collect($values)
                ->map(fn (mixed $value, string $key): EmployeeMovementChangeData => new EmployeeMovementChangeData(
                    label: $this->label($key),
                    value: $this->displayValue($value),
                ))
                ->values()
                ->all(),
            hasRestrictedCompensation: $hasRestrictedCompensation,
            createdByName: $movement->createdBy?->name ?? 'System',
            status: (string) $movement->status,
            statusLabel: $this->label((string) $movement->status),
        );
    }

    /**
     * Preserve the existing JSON contract while using the same field-visibility authority as HTML.
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function resourceValues(array $values, ?User $actor, ?Employee $employee): array
    {
        if ($actor !== null && $this->fieldVisibility->canViewCompensation($actor, $employee)) {
            return $values;
        }

        if (array_key_exists(self::COMPENSATION_KEY, $values)) {
            $values[self::COMPENSATION_KEY] = 'restricted';
        }

        return $values;
    }

    private function label(string $value): string
    {
        return match ($value) {
            'branch_id' => 'Branch',
            'project_id' => 'Project',
            'manager_employee_id' => 'Reporting manager',
            self::COMPENSATION_KEY => 'Monthly CTC',
            default => str($value)->replace('_', ' ')->title()->toString(),
        };
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Not set';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return is_scalar($value) ? (string) $value : 'Updated';
    }
}
