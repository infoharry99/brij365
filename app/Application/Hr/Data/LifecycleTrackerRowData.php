<?php

namespace App\Application\Hr\Data;

final readonly class LifecycleTrackerRowData
{
    public function __construct(
        public int $id,
        public int $employeeId,
        public string $employeeCode,
        public string $employeeName,
        public string $department,
        public string $designation,
        public string $employeeStatus,
        public string $eventType,
        public string $eventTypeLabel,
        public string $number,
        public string $status,
        public string $statusLabel,
        public string $statusTone,
        public string $eventDate,
        public string $eventDateLabel,
        public string $url,
    ) {}
}
