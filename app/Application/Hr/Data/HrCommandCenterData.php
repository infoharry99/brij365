<?php

namespace App\Application\Hr\Data;

final readonly class HrCommandCenterData
{
    public function __construct(
        public array $summary,
        public array $approvalInbox,
        public array $departmentHeadcount,
        public array $lifecycleDue,
        public array $complianceRisk,
        public array $abilities,
    ) {}

    public function toView(): array
    {
        return get_object_vars($this);
    }
}
