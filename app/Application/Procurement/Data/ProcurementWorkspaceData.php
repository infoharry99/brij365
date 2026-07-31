<?php

namespace App\Application\Procurement\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class ProcurementWorkspaceData
{
    /** @param array<string, mixed> $filters @param array<string, mixed> $dashboard @param array<int, mixed> $vendorScores */
    public function __construct(
        public string $activeRegister,
        public array $filters,
        public array $dashboard,
        public LengthAwarePaginator $vendors,
        public LengthAwarePaginator $requisitions,
        public LengthAwarePaginator $stockItems,
        public Collection $companies,
        public Collection $projects,
        public array $vendorTypes,
        public array $vendorStatuses,
        public array $requisitionStatuses,
        public array $priorities,
        public array $storeTypes,
        public array $stockStatuses,
        public bool $canCreateVendor,
        public bool $canCreateRequisition,
        public array $vendorScores,
    ) {}

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return get_object_vars($this);
    }
}
