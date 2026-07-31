<?php

namespace App\Application\Hr\Data;

final readonly class LeaveProcessingRuleSnapshotData
{
    /**
     * @param array<int, LeaveProcessingLeaveTypeRuleData> $leaveTypes
     */
    public function __construct(
        public array $leaveTypes,
        public string $settingKey,
        public string $encashmentTaxRate,
        public string $monthlyAccrualLabel,
        public string $yearEndLabel,
        public string $encashmentFormula,
    ) {}
}
