<?php

namespace App\Application\AfterSales\Actions;

use App\Application\AfterSales\Data\MaintenanceWorkOrderWorkspaceData;
use App\Domain\AfterSales\Services\AfterSalesRegister;
use App\Models\MaintenanceWorkOrder;
use App\Models\User;

final class ListMaintenanceWorkOrderWorkspace
{
    public function __construct(private readonly AfterSalesRegister $register) {}

    public function execute(User $actor, array $filters): MaintenanceWorkOrderWorkspaceData
    {
        return new MaintenanceWorkOrderWorkspaceData(
            workOrders: $this->register->workOrders($actor, $filters),
            filters: $filters,
            tickets: $this->register->openTickets($actor),
            assignees: $this->register->assignees($actor),
            vendors: $this->register->vendors($actor),
            statuses: ['planned' => 'Planned', 'scheduled' => 'Scheduled', 'completed' => 'Completed', 'cancelled' => 'Cancelled'],
            abilities: ['canCreateWorkOrder' => $actor->can('create', MaintenanceWorkOrder::class)],
        );
    }
}
