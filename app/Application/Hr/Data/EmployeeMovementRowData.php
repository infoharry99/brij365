<?php

namespace App\Application\Hr\Data;

final readonly class EmployeeMovementRowData
{
    /**
     * @param array<int, EmployeeMovementChangeData> $changes
     */
    public function __construct(
        public int $id,
        public string $number,
        public string $type,
        public string $typeLabel,
        public string $effectiveDate,
        public string $reason,
        public array $changes,
        public bool $hasRestrictedCompensation,
        public string $createdByName,
        public string $status,
        public string $statusLabel,
    ) {}
}
