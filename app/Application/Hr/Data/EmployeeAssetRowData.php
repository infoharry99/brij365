<?php

namespace App\Application\Hr\Data;

final readonly class EmployeeAssetRowData
{
    public function __construct(
        public int $id,
        public string $assetCode,
        public string $category,
        public string $name,
        public string $serialNumber,
        public string $status,
        public string $statusLabel,
        public string $statusTone,
        public string $condition,
        public string $conditionLabel,
        public string $conditionTone,
        public string $employeeName,
        public string $employeeCode,
        public string $employeeInitial,
        public string $employeeContext,
        public string $assignedOn,
        public string $recoveredOn,
        public string $estimatedValue,
        public string $workflowNote,
        public string $workflowActor,
        public string $workflowAt,
        public bool $canAssign,
        public bool $canRecover,
    ) {}
}
