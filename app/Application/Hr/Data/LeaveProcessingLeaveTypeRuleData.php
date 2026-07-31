<?php

namespace App\Application\Hr\Data;

final readonly class LeaveProcessingLeaveTypeRuleData
{
    public function __construct(
        public string $code,
        public string $annualEntitlementDays,
        public string $carryForwardLabel,
        public string $maxCarryForwardDays,
        public string $encashmentLabel,
    ) {}
}
