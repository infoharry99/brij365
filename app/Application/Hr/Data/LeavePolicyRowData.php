<?php

namespace App\Application\Hr\Data;

final readonly class LeavePolicyRowData
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public string $annualEntitlement,
        public string $paidLabel,
        public string $documentLabel,
        public string $halfDayLabel,
        public string $negativeBalanceLabel,
        public string $carryForwardLabel,
        public string $encashmentLabel,
        public string $approvalChain,
    ) {}
}
