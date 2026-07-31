<?php

namespace App\Application\AfterSales\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final readonly class MaintenanceWorkOrderWorkspaceData
{
    public function __construct(
        public LengthAwarePaginator $workOrders,
        public array $filters,
        public Collection $tickets,
        public Collection $assignees,
        public Collection $vendors,
        public array $statuses,
        public array $abilities,
    ) {}

    public function toView(): array
    {
        return array_merge(get_object_vars($this), [
            'canCreateWorkOrder' => $this->abilities['canCreateWorkOrder'] ?? false,
        ]);
    }
}
