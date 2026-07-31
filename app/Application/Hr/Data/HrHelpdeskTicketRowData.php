<?php

namespace App\Application\Hr\Data;

final readonly class HrHelpdeskTicketRowData
{
    /**
     * @param  array<int, string>  $attachmentNames
     */
    public function __construct(
        public int $id,
        public string $ticketNumber,
        public string $subject,
        public string $description,
        public string $employeeCode,
        public string $employeeName,
        public string $employeeInitial,
        public string $employeeContext,
        public string $category,
        public string $categoryLabel,
        public string $priority,
        public string $priorityLabel,
        public string $priorityTone,
        public string $status,
        public string $statusLabel,
        public string $statusTone,
        public string $raisedBy,
        public string $assignedTo,
        public string $createdAt,
        public string $resolvedAt,
        public string $closedAt,
        public string $resolutionSummary,
        public int $attachmentCount,
        public array $attachmentNames,
        public string $workflowNote,
        public string $workflowActor,
        public string $workflowAt,
        public bool $canManage,
        public bool $canClose,
    ) {}
}
