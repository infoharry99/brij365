<?php

namespace App\Application\Payroll\Data;

use Illuminate\Pagination\LengthAwarePaginator;

final readonly class PayrollWorkspaceData
{
    /** @param array<string, bool> $abilities */
    public function __construct(
        public string $activeRegister,
        public PayrollWorkspaceSummaryData $summary,
        public ?LengthAwarePaginator $components,
        public ?LengthAwarePaginator $structures,
        public ?LengthAwarePaginator $runs,
        public ?LengthAwarePaginator $batches,
        public ?LengthAwarePaginator $commissionRules,
        public ?LengthAwarePaginator $commissionRuns,
        public array $componentTypes,
        public array $runStatuses,
        public array $batchStatuses,
        public array $commissionRuleTypes,
        public array $commissionBases,
        public array $commissionRuleStatuses,
        public array $commissionRunStatuses,
        public array $commissionRuleOptions,
        public array $projectOptions,
        public array $abilities,
    ) {}

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return get_object_vars($this);
    }
}
