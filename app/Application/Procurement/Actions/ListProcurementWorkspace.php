<?php

namespace App\Application\Procurement\Actions;

use App\Application\Procurement\Data\ProcurementWorkspaceData;
use App\Domain\Procurement\Services\ProcurementWorkspaceRegister;
use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListProcurementWorkspace
{
    public function __construct(private readonly ProcurementWorkspaceRegister $register) {}

    /** @param array<string, mixed> $filters @param array<string, mixed>|null $dashboard */
    public function execute(User $user, array $filters, string $activeRegister, ?LengthAwarePaginator $vendors = null, ?LengthAwarePaginator $requisitions = null, ?LengthAwarePaginator $stockItems = null, ?array $dashboard = null): ProcurementWorkspaceData
    {
        $vendors ??= $this->register->vendors($user, [], 'vendors_page');

        return new ProcurementWorkspaceData(
            activeRegister: $activeRegister,
            filters: $filters,
            dashboard: $dashboard ?? $this->register->dashboard($user, []),
            vendors: $vendors->withQueryString(),
            requisitions: ($requisitions ?? $this->register->requisitions($user, [], 'requisitions_page'))->withQueryString(),
            stockItems: ($stockItems ?? $this->register->stockItems($user, [], 'stock_page'))->withQueryString(),
            companies: $this->register->companies($user),
            projects: $this->register->projects($user),
            vendorTypes: ['material' => 'Material', 'contractor' => 'Contractor', 'service' => 'Service', 'consultant' => 'Consultant'],
            vendorStatuses: ['active' => 'Active', 'inactive' => 'Inactive', 'blocked' => 'Blocked'],
            requisitionStatuses: ['submitted' => 'Submitted', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'],
            priorities: ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'],
            storeTypes: ['central' => 'Central store', 'site' => 'Site store'],
            stockStatuses: ['active' => 'Active', 'inactive' => 'Inactive'],
            canCreateVendor: $user->can('create', Vendor::class),
            canCreateRequisition: $user->can('create', PurchaseRequisition::class),
            vendorScores: $this->register->vendorScores($user, $vendors),
        );
    }
}
