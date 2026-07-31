<?php

namespace App\Application\Payroll\Data;

final readonly class PayrollWorkspaceSummaryData
{
    public function __construct(
        public int $totalRuns,
        public int $generatedRuns,
        public int $approvedRuns,
        public string $approvedNetPayable,
        public int $preparedBatches,
        public int $releasedBatches,
        public int $activeStructures,
        public int $activeComponents,
        public int $activeCommissionRules,
        public int $generatedCommissionRuns,
        public int $approvedCommissionRuns,
        public string $approvedCommissionTotal,
    ) {}
}
