<?php

namespace App\Application\Recruitment\Data;

final readonly class JobOpeningRowData
{
    public function __construct(
        public int $id,
        public string $code,
        public string $title,
        public string $designation,
        public string $department,
        public int $positions,
        public string $employmentType,
        public string $location,
        public string $targetDate,
        public string $status,
        public string $statusLabel,
        public string $statusTone,
        public string $createdBy,
        public string $reviewedBy,
        public ?string $budgetRange,
        public bool $canApprove,
        public bool $canReject,
    ) {}
}
