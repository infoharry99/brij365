<?php

namespace App\Application\Hr\Data;

final readonly class EmployeeDocumentRowData
{
    public function __construct(
        public int $id,
        public int $employeeId,
        public string $documentNumber,
        public string $title,
        public string $employeeCode,
        public string $employeeName,
        public string $employeeInitial,
        public string $employeeContext,
        public string $category,
        public int $version,
        public bool $isCurrent,
        public string $issueDate,
        public string $expiryDate,
        public string $expiryState,
        public string $expiryTone,
        public string $filename,
        public string $fileSize,
        public string $status,
        public string $statusLabel,
        public string $statusTone,
        public bool $canApprove,
        public bool $canDownload,
    ) {}
}
